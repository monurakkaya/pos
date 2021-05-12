<?php

namespace Mews\Pos\Gateways;

use GuzzleHttp\Client;
use Mews\Pos\Entity\Account\BoaPosAccount;
use Mews\Pos\Entity\Account\EstPosAccount;
use Mews\Pos\Entity\Card\CreditCardBoaPos;
use Mews\Pos\Entity\Card\CreditCardEstPos;
use Mews\Pos\Exceptions\NotImplementedException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class BoaPos
 */
class BoaPos extends AbstractGateway
{
    const LANG_TR = 'tr';
    const LANG_EN = 'en';

    const API_VERSION = '1.0.0';

    /**
     * @const string
     */
    public const NAME = 'BoaPos';

    /**
     * Response Codes
     *
     * @var array
     */
    protected $codes = [
        '00' => 'approved',
        '001' => 'bank_call',
        '002' => 'bank_call'
    ];

    /**
     * Transaction Types
     *
     * @var array
     */
    protected $types = [
        self::TX_PAY => 'Sale'
    ];


    /**
     * Currency mapping
     *
     * @var array
     */
    protected $currencies = [
        'TRY'       => '0949',
    ];

    /**
     * @var BoaPosAccount
     */
    protected $account;

    /**
     * @var CreditCardBoaPos|null
     */
    protected $card;

    /**
     * BoaPos constructor.
     *
     * @param array $config
     * @param BoaPosAccount $account
     * @param array $currencies
     */
    public function __construct($config, $account, array $currencies = [])
    {
        parent::__construct($config, $account, $currencies);
    }

    /**
     * @inheritDoc
     */
    public function createXML(array $data, $encoding = 'ISO-8859-1'): string
    {
        return parent::createXML(['KuveytTurkVPosMessage' => $data], $encoding);
    }

    /**
     * Create 3D Hash
     *
     * @return string
     */
    public function create3DHash()
    {

        $hashStr = $this->account->getMerchantId() . $this->order->id . $this->order->amount . $this->order->success_url . $this->order->fail_url . $this->account->getUsername() . $this->account->getHashedPassword();
        return base64_encode(sha1($hashStr, true));
    }

    /**
     * Create 3D Response Hash
     *
     * @return string
     */
    public function create3DResponseHash()
    {

        $hashStr = $this->account->getMerchantId() . $this->order->id . $this->order->amount . $this->account->getUsername() . $this->account->getHashedPassword();
        return base64_encode(sha1($hashStr, true));
    }


    /**
     * @inheritDoc
     */
    public function make3DPayment()
    {
        $request = Request::createFromGlobals();
        $response = json_decode(json_encode($this->XMLStringToObject(urldecode($request->request->get('AuthenticationResponse')))), true);
        if ($response['ResponseCode'] === '00') {
            $contents = $this->create3DPaymentXML($response);
            $this->send($contents, $this->get3DGatewayURL());
            $this->data = $this->XMLStringToObject($this->data);
        }

        $this->response = $this->map3DPaymentData($response, $this->data);

        return $this;
    }

    /**
     * @inheritDoc
     */
    protected function map3DPaymentData($raw3DAuthResponseData, $rawPaymentResponseData)
    {
        $transactionSecurity = 'MPI fallback';
        if ($this->getResponseCode() === '00') {
            $transactionSecurity = $rawPaymentResponseData->VPosMessage->TransactionSecurity == 3 ? '3D Secure': '2D';
        }

        $paymentResponseData = $this->mapPaymentResponse($rawPaymentResponseData);

        $threeDResponse = [
            'transaction_security' => $transactionSecurity,
            '3d_all' => $raw3DAuthResponseData
        ];

        return (object) array_merge($threeDResponse, $paymentResponseData);

    }

    /**
     * @inheritDoc
     */
    public function make3DPayPayment()
    {
        $request = Request::createFromGlobals();

        $this->response = $this->map3DPayResponseData($request->request->all());

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function make3DHostPayment()
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function get3DFormData()
    {
        $data = [];

        if ($this->order) {
            $xml = $this->createRegularPaymentXML();
            $this->send($xml);
            $data = $this->data;
        }
        return $data;
    }

    /**
     * @inheritDoc
     */
    public function send($contents, $url = null)
    {
        if (is_null($url)) {
            $url = $this->getApiURL();
        } else {
            $url = $this->get3DGatewayURL();
        }
        $client = new Client();

        $response = $client->request('POST', $url, [
            'headers' => [
                'Content-type' => 'application/xml'
            ],
            'body' => $contents
        ]);

        $this->data = $response->getBody()->getContents();

        return $this;
    }





    /**
     * @return mixed
     */
    public function getAccount()
    {
        return $this->account;
    }

    /**
     * @return CreditCardBoaPos|null
     */
    public function getCard()
    {
        return $this->card;
    }

    /**
     * @inheritDoc
     */
    public function createRegularPaymentXML(): string
    {
        $requestData = [
            'APIVersion' => self::API_VERSION,
            'OkUrl' => $this->order->success_url,
            'FailUrl' => $this->order->fail_url,
            'CardNumber' => $this->card->getNumber(),
            'CardExpireDateMonth' => $this->card->getExpireMonth(),
            'CardExpireDateYear' => $this->card->getExpireYear(),
            'CardCVV2' => $this->card->getCvv(),
            'CardHolderName' => $this->card->getHolderName(),
            'BatchID' => '0',
            'DisplayAmount' => $this->order->amount,
            'HashData' => $this->create3DHash(),
            'MerchantId' => $this->account->getMerchantId(),
            'CustomerId' => $this->account->getCustomerId(),
            'UserName' => $this->account->getUsername(),
            'TransactionType' => $this->type,
            'InstallmentCount' => $this->order->installment,
            'Amount' => $this->order->amount,
            'CurrencyCode' => $this->order->currency,
            'MerchantOrderId' => $this->order->id,
            'TransactionSecurity' => 3,
            'CardType' => 'MasterCard'
        ];

        return $this->createXML($requestData);
    }


    /**
     * @inheritDoc
     */
    public function create3DPaymentXML($responseData)
    {
        $requestData = [
            'HashData' => $this->create3DResponseHash(),
            'MerchantId' => $this->account->getMerchantId(),
            'CustomerId' => $this->account->getCustomerId(),
            'UserName' => $this->account->getUsername(),
            'TransactionType' => $this->type,
            'InstallmentCount' => $this->order->installment,
            'Amount' => $this->order->amount,
            'CurrencyCode' => $this->order->currency,
            'MerchantOrderId' => $this->order->id,
            'TransactionSecurity' => 3,
            'KuveytTurkVPosAdditionalData' => [
                'AdditionalData' => [
                    'Key' => 'MD',
                    'Data' => (string)$responseData['MD']
                ]
            ]
        ];

        return $this->createXML($requestData);
    }


    /**
     * Get ResponseCode
     *
     * @return string|null
     */
    protected function getResponseCode()
    {
        return isset($this->data->ResponseCode) ? (string) $this->data->ResponseCode : null;
    }



    /**
     * @inheritDoc
     */
    protected function map3DPayResponseData($raw3DAuthResponseData)
    {
        $status = 'declined';

        if ($this->check3DHash($raw3DAuthResponseData) && $raw3DAuthResponseData['ProcReturnCode'] === '00') {
            if (in_array($raw3DAuthResponseData['mdStatus'], [1, 2, 3, 4])) {
                $status = 'approved';
            }
        }

        $transactionSecurity = 'MPI fallback';
        if ('approved' === $status) {
            if ($raw3DAuthResponseData['mdStatus'] == '1') {
                $transactionSecurity = 'Full 3D Secure';
            } elseif (in_array($raw3DAuthResponseData['mdStatus'], [2, 3, 4])) {
                $transactionSecurity = 'Half 3D Secure';
            }
        }

        return (object) [
            'id' => $raw3DAuthResponseData['AuthCode'],
            'trans_id' => $raw3DAuthResponseData['TransId'],
            'auth_code' => $raw3DAuthResponseData['AuthCode'],
            'host_ref_num' => $raw3DAuthResponseData['HostRefNum'],
            'response' => $raw3DAuthResponseData['Response'],
            'order_id' => $raw3DAuthResponseData['oid'],
            'transaction_type' => $this->type,
            'transaction' => $this->type,
            'transaction_security' => $transactionSecurity,
            'code' => $raw3DAuthResponseData['ProcReturnCode'],
            'md_status' => $raw3DAuthResponseData['mdStatus'],
            'status' => $status,
            'status_detail' => isset($this->codes[$raw3DAuthResponseData['ProcReturnCode']]) ? $raw3DAuthResponseData['ProcReturnCode'] : null,
            'hash' => $raw3DAuthResponseData['HASH'],
            'rand' => $raw3DAuthResponseData['rnd'],
            'hash_params' => $raw3DAuthResponseData['HASHPARAMS'],
            'hash_params_val' => $raw3DAuthResponseData['HASHPARAMSVAL'],
            'masked_number' => $raw3DAuthResponseData['maskedCreditCard'],
            'month' => $raw3DAuthResponseData['Ecom_Payment_Card_ExpDate_Month'],
            'year' => $raw3DAuthResponseData['Ecom_Payment_Card_ExpDate_Year'],
            'amount' => $raw3DAuthResponseData['amount'],
            'currency' => $raw3DAuthResponseData['currency'],
            'tx_status' => $raw3DAuthResponseData['txstatus'],
            'eci' => $raw3DAuthResponseData['eci'],
            'cavv' => $raw3DAuthResponseData['cavv'],
            'xid' => $raw3DAuthResponseData['xid'],
            'error_code' => $raw3DAuthResponseData['ErrCode'],
            'error_message' => $raw3DAuthResponseData['ErrMsg'],
            'md_error_message' => $raw3DAuthResponseData['mdErrorMsg'],
            'name' => $raw3DAuthResponseData['firmaadi'],
            'email' => $raw3DAuthResponseData['Email'],
            'campaign_url' => null,
            'extra' => $raw3DAuthResponseData['Extra'],
            'all' => $raw3DAuthResponseData,
        ];
    }



    /**
     * @inheritDoc
     */
    protected function mapPaymentResponse($responseData)
    {
        $status = 'declined';
        if ($this->getResponseCode() === '00') {
            $status = 'approved';
        }

       return [
            'id' => isset($responseData->ProvisionNumber) ? $this->printData($responseData->ProvisionNumber) : null,
            'order_id' => isset($responseData->OrderId) ? $this->printData($responseData->OrderId) : null,
            'trans_id' => isset($responseData->ProvisionNumber) ? $this->printData($responseData->ProvisionNumber) : null,
            'response' => isset($responseData->Response) ? $this->printData($responseData->Response) : null,
            'transaction_type' => $this->type,
            'transaction' => $this->type,
            'status' => $status,
            'error_code' => $this->getResponseCode() !== '00' ? $this->getResponseCode() : null,
            'error_message' => $this->getResponseCode() !== '00' ? $this->printData($responseData->ResponseMessage) : null,
            'all' => $responseData,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function mapHistoryResponse($rawResponseData)
    {
        $status = 'declined';
        if ($this->getProcReturnCode() === '00') {
            $status = 'approved';
        }

        return (object) [
            'order_id' => isset($rawResponseData->OrderId) ? $this->printData($rawResponseData->OrderId) : null,
            'response' => isset($rawResponseData->Response) ? $this->printData($rawResponseData->Response) : null,
            'proc_return_code' => isset($rawResponseData->ProcReturnCode) ? $this->printData($rawResponseData->ProcReturnCode) : null,
            'error_message' => isset($rawResponseData->ErrMsg) ? $this->printData($rawResponseData->ErrMsg) : null,
            'num_code' => isset($rawResponseData->Extra->NUMCODE) ? $this->printData($rawResponseData->Extra->NUMCODE) : null,
            'trans_count' => isset($rawResponseData->Extra->TRXCOUNT) ? $this->printData($rawResponseData->Extra->TRXCOUNT) : null,
            'status' => $status,
            'status_detail' => $this->getStatusDetail(),
            'all' => $rawResponseData,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function preparePaymentOrder(array $order)
    {
        // Installment
        $installment = 0;
        if (isset($order['installment']) && $order['installment'] > 1) {
            $installment = (int) $order['installment'];
        }

        // Order
        return (object) array_merge($order, [
            'installment'   => $installment,
            'amount'        => self::amountFormat($order['amount']),
            'currency'      => $this->mapCurrency($order['currency']),
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function preparePostPaymentOrder(array $order)
    {
        return (object) [
            'id' => $order['id'],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareStatusOrder(array $order)
    {
        return (object) $order;
    }

    /**
     * @inheritDoc
     */
    protected function prepareHistoryOrder(array $order)
    {
        return (object) $order;
    }

    /**
     * @inheritDoc
     */
    protected function prepareCancelOrder(array $order)
    {
        return (object) $order;
    }

    /**
     * @inheritDoc
     */
    protected function prepareRefundOrder(array $order)
    {
        return (object) $order;
    }

    /**
     * Amount Formatter
     *
     * @param double $amount
     *
     * @return int
     */
    public static function amountFormat($amount)
    {
        return round($amount, 2) * 100;
    }



    /**
     * @inheritDoc
     */
    public function history(array $meta)
    {

    }


    /**
     * @inheritDoc
     */
    public function createRegularPostXML()
    {

    }


    /**
     * @inheritDoc
     */
    public function createStatusXML()
    {

    }

    /**
     * @inheritDoc
     */
    public function createHistoryXML($customQueryData)
    {

    }

    /**
     * @inheritDoc
     */
    public function createCancelXML()
    {

    }

    /**
     * @inheritDoc
     */
    public function createRefundXML()
    {

    }

    /**
     * Get Status Detail Text
     *
     * @return string|null
     */
    protected function getStatusDetail()
    {

    }
    /**
     * @inheritDoc
     */
    protected function mapRefundResponse($rawResponseData)
    {

    }

    /**
     * @inheritDoc
     */
    protected function mapCancelResponse($rawResponseData)
    {

    }

    /**
     * @inheritDoc
     */
    protected function mapStatusResponse($rawResponseData)
    {

    }

}
