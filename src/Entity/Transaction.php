<?php

namespace AppBundle\Entity;

use Algolia\AlgoliaSearchBundle\Mapping\Annotation as Algolia;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="AppBundle\Repository\TransactionRepository")
 * @ORM\Table(indexes={
 *     @ORM\Index(name="transaction_result_idx", columns={"paybox_result_code"})
 * })
 *
 * @Algolia\Index(autoIndex=false)
 */
class Transaction
{
    public const PAYBOX_SUCCESS = '00000';
    public const PAYBOX_CONNECTION_FAILED = '00001';
    public const PAYBOX_INTERNAL_ERROR = '00003';
    public const PAYBOX_CARD_NUMBER_INVALID = '00004';
    public const PAYBOX_CARD_END_DATE_INVALID = '00008';
    public const PAYBOX_CARD_UNAUTHORIZED = '000021';
    public const PAYBOX_PAYMENT_PAGE_TIMEOUT = '000030';

    public const TYPE_PAYBOX = 'paybox';
    public const TYPE_CHEQUE = 'cheque';
    public const TYPE_TRANSFER = 'transfer';

    /**
     * @var int
     *
     * @ORM\Column(type="integer", options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string|null
     *
     * @ORM\Column(length=100, nullable=true)
     */
    private $payboxResultCode;

    /**
     * @var string|null
     *
     * @ORM\Column(length=100, nullable=true)
     */
    private $payboxAuthorizationCode;

    /**
     * @var array|null
     *
     * @ORM\Column(type="json_array", nullable=true)
     */
    private $payboxPayload;

    /**
     * @var \DateTimeImmutable|null
     *
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    private $payboxDateTime;

    /**
     * @var string|null
     *
     * @ORM\Column(unique=true, nullable=true)
     */
    private $payboxTransactionId;

    /**
     * @var string|null
     *
     * @ORM\Column(nullable=true)
     */
    private $payboxSubscriptionId;

    /**
     * @var string
     *
     * @ORM\Column
     */
    private $type;

    /**
     * @var \DateTimeImmutable
     *
     * @ORM\Column(type="datetime_immutable")
     */
    private $createdAt;

    /**
     * @var Donation
     *
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Donation")
     */
    private $donation;

    public function __construct(Donation $donation, array $payboxPayload)
    {
        $this->donation = $donation;
        $this->createdAt = new \DateTimeImmutable();
        $this->payboxPayload = $payboxPayload;
        $this->type = self::TYPE_PAYBOX;
        $this->payboxResultCode = $payboxPayload['result'];
        $this->payboxAuthorizationCode = $payboxPayload['authorization'] ?: null;
        $this->payboxSubscriptionId = $payboxPayload['subscription'] ?: null;
        $this->payboxTransactionId = $payboxPayload['transaction'] ?: null;

        if (isset($payboxPayload['date'], $payboxPayload['time'])) {
            $this->payboxDateTime = \DateTimeImmutable::createFromFormat(
                'dmYH:i:s',
                $payboxPayload['date'].$payboxPayload['time']
            );
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPayboxResultCode(): ?string
    {
        return $this->payboxResultCode;
    }

    public function getPayboxAuthorizationCode(): ?string
    {
        return $this->payboxAuthorizationCode;
    }

    public function getPayboxPayload(): ?array
    {
        return $this->payboxPayload;
    }

    public function getDonation(): Donation
    {
        return $this->donation;
    }

    public function getPayboxDateTime(): ?\DateTimeImmutable
    {
        return $this->payboxDateTime;
    }

    public function getPayboxTransactionId(): ?string
    {
        return $this->payboxTransactionId;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPayboxSubscriptionId(): ?string
    {
        return $this->payboxSubscriptionId;
    }

    public function isSuccessful(): bool
    {
        return self::PAYBOX_SUCCESS === $this->payboxResultCode;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
