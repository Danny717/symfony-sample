<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use App\Validator as CustomAssert;

class ChangeBalanceDto implements ValidatableInterface
{
    /**
     * @var integer
     * @CustomAssert\ExistsEntity(
     *     entityClass="App\Entity\User",
     *     field="id",
     *     message="Пользователь не найден"
     * )
     * @Assert\NotBlank(
     *     message="Не указан ID пользователя"
     * )
     * @Assert\Type("integer")
     */
    private $user_id;

    /**
     * @CustomAssert\ExistsEntity(
     *     entityClass="App\Entity\Currency",
     *     field="name",
     *     message="Валюта не найдена"
     * )
     * @Assert\NotBlank(
     *     message="Не указана валюта"
     * )
     * @Assert\Type("string")
     */
    private $currency_name;

    /**
     * @var float
     * @Assert\NotBlank(
     *     message="Не указана сумма"
     * )
     * @Assert\Type("float")
     */
    private $amount;

    /**
     * @Assert\Length(max=255)
     */
    private $comment;

    /**
     * @return mixed
     */
    public function getUserId()
    {
        return $this->user_id;
    }

    /**
     * @return mixed
     */
    public function getCurrencyName()
    {
        return $this->currency_name;
    }

    /**
     * @return mixed
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @return mixed
     */
    public function getComment()
    {
        return $this->comment;
    }
}