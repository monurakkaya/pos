<?php


namespace Mews\Pos\Gateways;


use DOMDocument;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\Get724PosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Entity\Card\CreditCardGet724Pos;
use Symfony\Component\HttpFoundation\Request;

class Get724Pos extends AbstractGateway
{

    /**
     * @var Get724PosAccount
     */
    protected $account;

    /**
     * @var CreditCardGet724Pos|null
     */
    protected $card;

    /**
     * Transaction Types
     *
     * @var array
     */
    protected $types = [
        self::TX_PAY => 'Sale',
        self::TX_PRE_PAY => 'Auth',
        self::TX_POST_PAY => 'Capture',
        self::TX_CANCEL => 'Cancel',
        self::TX_REFUND => 'Refund',
        self::TX_STATUS => 'ORDERSTATUS',
        self::TX_HISTORY => 'ORDERHISTORY',
    ];

    /**
     * Currency mapping
     *
     * @var array
     */
    protected $currencies = [
        'TRY'       => 949,
        'USD'       => 840,
        'EUR'       => 978,
        'GBP'       => 826,
        'JPY'       => 392,
    ];

    /**
     * EstPos constructor.
     *
     * @param array $config
     * @param Get724PosAccount $account
     * @param array $currencies
     */
    public function __construct($config, $account, array $currencies = [])
    {
        parent::__construct($config, $account, $currencies);
    }

    public function getAccount()
    {
        return $this->account;
    }

    public function getCard()
    {
        return $this->card;
    }
    /**
     * @inheritDoc
     */
    public function createXML(array $data, $encoding = 'ISO-8859-9'): string
    {
        return parent::createXML(['VposRequest' => $data], $encoding);
    }

    public function createRegularPaymentXML()
    {
        $requestData = [
            'TransactionType' => $this->type,
            'MerchantId' => $this->account->getMerchantId(),
            'TerminalNo' => $this->account->getTerminalNo(),
            'Password' => $this->account->getPassword(),
            'Pan' => $this->card->getNumber(),
            'Expiry' => $this->card->getExpirationDate(),
            'CVV' => $this->card->getCvv(),
            'CardHoldersName' => $this->card->getHolderName(),
            'CurrencyAmount' => $this->order->amount,
            'CurrencyCode' => $this->order->currency,
            'NumberOfInstallments' => $this->order->installment,
            'BrandName' => $this->card->getType(),
            'ClientIp' => $this->order->ip,
            'OrderId' => $this->order->id,
            'TransactionDeviceSource' => 0
        ];

        return $this->createXML($requestData);
    }

    public function createRegularPostXML()
    {
        // TODO: Implement createRegularPostXML() method.
    }

    public function createHistoryXML($customQueryData)
    {
        // TODO: Implement createHistoryXML() method.
    }

    public function createStatusXML()
    {
        // TODO: Implement createStatusXML() method.
    }

    public function createCancelXML()
    {
        // TODO: Implement createCancelXML() method.
    }

    public function createRefundXML()
    {
        // TODO: Implement createRefundXML() method.
    }

    public function create3DPaymentXML($responseData)
    {
        $amount = substr_replace($responseData['PurchAmount'],'.',-2,-2);
        $requestData = [
            'MerchantId' => $this->account->getMerchantId(),
            'Password' => $this->account->getPassword(),
            'TerminalNo' => $this->account->getTerminalNo(),
            'TransactionType' => $this->type,
            'TransactionDeviceSource' => '0',
            'CurrencyAmount' => $amount,
            'CurrencyCode' => $responseData['PurchCurrency'],
            'Pan' => $responseData['Pan'],
            'Expiry' => '20'.$responseData['Expiry'],
            'ClientIp' => $this->order->ip,
            'CAVV' => $responseData['Cavv'],
            'ECI' => $responseData['Eci'],
            'ReferenceTransactionId' => $responseData['VerifyEnrollmentRequestId'],
            'MpiTransactionId' => $responseData['VerifyEnrollmentRequestId'],
        ];

        return $this->createXML($requestData);
    }

    private function checkIfCardSupports3d()
    {
        $contents = [
            'MerchantId' => $this->account->getMerchantId(),
            'MerchantPassword' => $this->account->getPassword(),
            'Pan' => $this->card->getNumber(),
            'ExpiryDate' => $this->card->getExpirationDateShort(),
            'PurchaseAmount' => $this->order->amount,
            'Currency' => $this->order->currency,
            'SessionInfo' => $this->order->rand,
            'InstallmentCount' => $this->order->installment,
            'BrandName' => $this->card->getType() ?? 100,
            'SuccessUrl' => $this->order->success_url,
            'FailureUrl' => $this->order->fail_url,
            'VerifyEnrollmentRequestId' => $this->order->id
        ];

        $this->send($contents, $this->get3DGatewayURL());
        return $this->map3dSupportedResponse();
    }

    private function map3dSupportedResponse()
    {
        $status = $this->data->Message->VERes->Status ?? 'N';
        $ACSUrl = $this->data->Message->VERes->ACSUrl ?? '';
        $PaReq = $this->data->Message->VERes->PaReq ?? '';
        $TermUrl = $this->data->Message->VERes->TermUrl ?? '';
        $MD = $this->data->Message->VERes->MD ?? '';
        return [
            'status' => $status === 'Y',
            'gateway' => $ACSUrl,
            'inputs' => [
                'TermUrl' => $TermUrl,
                'PaReq' => $PaReq,
                'MD' => $MD,
            ]
        ];
    }

    public function get3DFormData()
    {
        $inputs = [];
        $data = null;

        if ($this->card && $this->order) {
            $mpiRequest = $this->checkIfCardSupports3d();
            if ($mpiRequest['status'] === true) {
                unset($mpiRequest['status']);
                return $mpiRequest;
            }
        } else {

        }

        return [
            'gateway' => $this->get3DGatewayURL(),
            'inputs' => $inputs
        ];
    }

    /**
     * @inheritDoc
     */
    protected function preparePaymentOrder(array $order)
    {
        // Installment
        $installment = '';
        if (isset($order['installment']) && $order['installment'] > 1) {
            $installment = $order['installment'];
        }

        return (object) array_merge($order, [
            'installment'   => $installment,
            'amount'        => self::formatAmount($order['amount']),
            'currency'      => $this->mapCurrency($order['currency']),
        ]);
    }

    /**
     * Get amount
     * formats 10.1 to 10.10
     * @param string $amount
     *
     * @return int
     */
    public static function formatAmount($amount)
    {
        return number_format($amount, 2, '.', '');
    }

    protected function preparePostPaymentOrder(array $order)
    {
        // TODO: Implement preparePostPaymentOrder() method.
    }

    protected function prepareStatusOrder(array $order)
    {
        // TODO: Implement prepareStatusOrder() method.
    }

    protected function prepareHistoryOrder(array $order)
    {
        // TODO: Implement prepareHistoryOrder() method.
    }

    protected function prepareCancelOrder(array $order)
    {
        // TODO: Implement prepareCancelOrder() method.
    }

    protected function prepareRefundOrder(array $order)
    {
        // TODO: Implement prepareRefundOrder() method.
    }

    protected function map3DPaymentData($raw3DAuthResponseData, $rawPaymentResponseData)
    {
        $paymentResponseData = $this->mapPaymentResponse($rawPaymentResponseData);
        return (object) array_merge($raw3DAuthResponseData, $paymentResponseData);
    }

    protected function map3DPayResponseData($raw3DAuthResponseData)
    {
        // TODO: Implement map3DPayResponseData() method.
    }

    protected function mapPaymentResponse($responseData)
    {
        $status = 'declined';
        if ($this->getResultCode() === '0000') {
            $status = 'approved';
        }
        return [
            'id' => isset($responseData->AuthCode) ? $this->printData($responseData->AuthCode) : null,
            'order_id' => isset($responseData->ReferenceTransactionId) ? $this->printData($responseData->ReferenceTransactionId) : null,
            'trans_id' => isset($responseData->TransactionId) ? $this->printData($responseData->TransactionId) : null,
            'response' => isset($responseData->Response) ? $this->printData($responseData->Response) : null,
            'transaction_type' => $this->type,
            'transaction' => $this->type,
            'auth_code' => isset($responseData->AuthCode) ? $this->printData($responseData->AuthCode) : null,
            'proc_return_code' => isset($responseData->ResultCode) ? $this->printData($responseData->ResultCode) : null,
            'code' => isset($responseData->ResultCode) ? $this->printData($responseData->ResultCode) : null,
            'status' => $status,
            'status_detail' => isset($responseData->ResultDetail) ? $this->printData($responseData->ResultDetail) : null,
            'all' => $responseData,
        ];
    }

    /**
     * Get ProcReturnCode
     *
     * @return string|null
     */
    protected function getResultCode()
    {
        return isset($this->data->ResultCode) ? (string) $this->data->ResultCode : null;
    }

    protected function mapRefundResponse($rawResponseData)
    {
        // TODO: Implement mapRefundResponse() method.
    }

    protected function mapCancelResponse($rawResponseData)
    {
        // TODO: Implement mapCancelResponse() method.
    }

    protected function mapStatusResponse($rawResponseData)
    {
        // TODO: Implement mapStatusResponse() method.
    }

    protected function mapHistoryResponse($rawResponseData)
    {
        // TODO: Implement mapHistoryResponse() method.
    }

    public function make3DPayment()
    {
        return $this->make3DPayPayment();
    }

    public function make3DPayPayment()
    {
        $request = Request::createFromGlobals();

        if ($this->check3DHash($request->request->all())) {
            $contents = $this->create3DPaymentXML($request->request->all());
            $this->send(['prmstr' => $contents]);
        }

        $this->response = $this->map3DPaymentData($request->request->all(), $this->data);
        return $this;
    }

    private function check3DHash($request): bool
    {
        return $request['Status'] === 'Y';
    }

    public function make3DHostPayment()
    {
        return $this->make3DPayPayment();
    }

    public function send($contents, $url = null)
    {
        if (is_null($url)) {
            $url = $this->getApiURL();
        }
        $client = new Client();

        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        $response = $client->request('POST', $url, [
            'headers' => $headers,
            'form_params' => $contents
        ]);


        $this->data = $this->XMLStringToObject($response->getBody()->getContents());

        return $this;
    }
}