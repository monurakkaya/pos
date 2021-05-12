<?php


namespace Mews\Pos\Entity\Account;


class BoaPosAccount extends AbstractPosAccount
{
    private $merchantId;

    public function __construct(
        string $bank,
        string $model,
        string $customerId,
        string $username,
        string $password,
        string $merchantId,
        string $lang,
        ?string $storeKey = null
    )
    {
        parent::__construct($bank, $model, $customerId, $username, $password, $lang, $storeKey);
        $this->merchantId = $merchantId;
    }

    public function getHashedPassword(): string
    {
        return base64_encode(sha1($this->getPassword(),"ISO-8859-9"));
    }

    public function getMerchantId()
    {
        return $this->merchantId;
    }

    public function getCustomerId()
    {
        return $this->getClientId();
    }
}