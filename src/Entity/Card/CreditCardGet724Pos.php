<?php


namespace Mews\Pos\Entity\Card;


class CreditCardGet724Pos extends AbstractCreditCard
{

    public function getExpirationDate(): string
    {
        return $this->getExpireYear().$this->getExpireMonth();
    }

    public function getExpirationDateShort(): string
    {
        return $this->getExpireYear(2).$this->getExpireMonth();
    }

    /**
     * returns exp year in 4 digit format
     * @param int $digits
     * @return string
     */
    public function getExpireYear($digits = 4): string
    {
        return $this->expireYear->format(
            $digits === 4 ? 'Y' : 'y'
        );
    }
}