<?php


namespace Mews\Pos\Entity\Account;


class Get724PosAccount extends AbstractPosAccount
{
    protected string $merchantId;
    protected string $terminalNo;

    public function __construct(
        string $bank,
        string $model,
        string $merchantId,
        string $terminalNo,
        string $password,
        string $lang,
        ?string $storeKey = null
    ) {
        parent::__construct($bank, $model, '', '', $password, $lang, $storeKey);
        $this->model = $model;
        $this->terminalNo = $terminalNo;
        $this->merchantId = $merchantId;
    }

    /**
     * @return string
     */
    public function getTerminalNo(): string
    {
        return $this->terminalNo;
    }

    /**
     * @return string
     */
    public function getMerchantId(): string
    {
        return $this->merchantId;
    }
}