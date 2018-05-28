<?php

namespace AppBundle\Donation;

use AppBundle\Controller\EnMarche\DonationController;
use AppBundle\Entity\Adherent;
use AppBundle\Entity\Donation;
use AppBundle\Entity\Transaction;
use AppBundle\Exception\InvalidDonationCallbackException;
use AppBundle\Exception\InvalidDonationPayloadException;
use AppBundle\Exception\InvalidDonationStatusException;
use AppBundle\Membership\MembershipRegistrationProcess;
use Cocur\Slugify\Slugify;
use AppBundle\Exception\InvalidPayboxPaymentSubscriptionValueException;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DonationRequestUtils
{
    private const CALLBACK_TOKEN = 'donation_callback_token';
    private const STATUS_TOKEN = 'donation_status_token';
    private const RETRY_TOKEN = 'donation_retry_token';
    private const RETRY_PAYLOAD = 'donation_retry_payload';
    private const PAYBOX_SUCCESS = 'donation_paybox_success';
    private const PAYBOX_UNKNOWN = 'donation_paybox_unknown';
    private const PAYBOX_STATUSES = [
        // Success
        Transaction::PAYBOX_SUCCESS => self::PAYBOX_SUCCESS,

        // Platform or authorization center error
        Transaction::PAYBOX_CONNECTION_FAILED => 'paybox',
        Transaction::PAYBOX_INTERNAL_ERROR => 'paybox',

        // Invalid card number/validity
        Transaction::PAYBOX_CARD_NUMBER_INVALID => 'invalid-card',
        Transaction::PAYBOX_CARD_END_DATE_INVALID => 'invalid-card',
        Transaction::PAYBOX_CARD_UNAUTHORIZED => 'invalid-card',

        // Timeout
        Transaction::PAYBOX_PAYMENT_PAGE_TIMEOUT => 'timeout',

        // Other
        self::PAYBOX_UNKNOWN => 'error',
    ];
    private const SESSION_KEY = 'donation_request';

    private $locator;
    private $slugify;
    private $membershipRegistrationProcess;

    public function __construct(ServiceLocator $donationRequestUtilsLocator, Slugify $slugify, MembershipRegistrationProcess $membershipRegistrationProcess)
    {
        $this->locator = $donationRequestUtilsLocator;
        $this->slugify = $slugify;
        $this->membershipRegistrationProcess = $membershipRegistrationProcess;
    }

    /**
     * @throws InvalidDonationPayloadException
     */
    public function createFromRequest(Request $request, ?Adherent $currentUser): DonationRequest
    {
        $duration = $this->getDuration($request);

        if ($donation = $this->getSession()->get(static::SESSION_KEY)) {
            $donation->setDuration($duration);

            return $donation;
        }

        $clientIp = $request->getClientIp();
        $amount = (float) $request->query->get('montant');

        if (!PayboxPaymentSubscription::isValid($duration)) {
            throw new InvalidPayboxPaymentSubscriptionValueException($duration);
        }

        if ($currentUser) {
            $donation = DonationRequest::createFromAdherent($currentUser, $clientIp, $amount, $duration);
        } else {
            $donation = new DonationRequest(Uuid::uuid4(), $clientIp, $amount, $duration);
        }

        if ($request->query->has(self::RETRY_PAYLOAD)) {
            return $this->hydrateFromRetryPayload($donation, $request->query->get(self::RETRY_PAYLOAD, '{}'));
        }

        return $donation;
    }

    public function getDuration(Request $request): int
    {
        return $request->query->getInt('abonnement', PayboxPaymentSubscription::NONE);
    }

    public function startDonationRequest(DonationRequest $donationRequest): void
    {
        $this->getSession()->set(static::SESSION_KEY, $donationRequest);
    }

    public function terminateDonationRequest(): void
    {
        $this->getSession()->remove(static::SESSION_KEY);
    }

    public function buildCallbackParameters()
    {
        return ['_callback_token' => $this->getTokenManager()->getToken(self::CALLBACK_TOKEN)];
    }

    public function extractPayboxResultFromCallback(Request $request, string $token): array
    {
        $this->validateCallback($token);

        $data = array_merge($request->query->all(), [
            'authorization' => $request->query->get('authorization'),
            'result' => $request->query->get('result'),
        ]);

        unset($data['id'], $data['Sign']);

        return $data;
    }

    public function createRetryPayload(Donation $donation, Request $request): array
    {
        $this->validateCallbackStatus($request);

        $payload = $donation->getRetryPayload();
        $payload['_retry_token'] = (string) $this->getTokenManager()->getToken(self::RETRY_TOKEN);

        return [
            self::RETRY_PAYLOAD => json_encode($payload),
            'montant' => $donation->getAmountInEuros(),
        ];
    }

    public function createCallbackStatus(Transaction $transaction): array
    {
        $code = self::PAYBOX_STATUSES[$transaction->getPayboxResultCode()] ?? self::PAYBOX_STATUSES[self::PAYBOX_UNKNOWN];

        return [
            'code' => $code,
            'uuid' => $transaction->getDonation()->getUuid()->toString(),
            'is_registration' => $this->membershipRegistrationProcess->isStarted(),
            'status' => self::PAYBOX_SUCCESS === $code ? DonationController::RESULT_STATUS_EFFECTUE : DonationController::RESULT_STATUS_ERREUR,
            '_status_token' => (string) $this->getTokenManager()->getToken(self::STATUS_TOKEN),
        ];
    }

    public function buildDonationReference(UuidInterface $uuid, string $fullName): string
    {
        $str = sprintf(
            '%s_%s',
            $uuid,
            $this->slugify->slugify($fullName)
        );

        return $str;
    }

    /**
     * @param Donation[] $donations
     *
     * @return int amount in cents
     */
    public function estimateAmountRemainingForSubscriptions(array $donations, \DateTimeInterface $date = null): int
    {
        $totalAmount = 0;

        foreach ($donations as $donation) {
            $totalAmount += $this->estimateAmountRemaining($donation->getAmount(), $donation->getCreatedAt(), $date);
        }

        return $totalAmount;
    }

    public function estimateAmountRemaining(int $amount, \DateTimeInterface $donationStart, \DateTimeInterface $date = null): int
    {
        if ($nbIteration = $this->estimateNbIterationBeforeNextFiscalYear($donationStart, $date)) {
            return $amount * $nbIteration;
        }

        return 0;
    }

    public function estimateNbIterationBeforeNextFiscalYear(\DateTimeInterface $donationStart, \DateTimeInterface $date = null): int
    {
        if (!$date) {
            $date = new \DateTime();
        }
        $nextYear = \DateTime::createFromFormat('Y/m/d H:i:s', ((int) $date->format('Y') + 1).'/01/01 00:00:00');
        $diff = $date->diff($nextYear);
        $nb = $diff->m;

        if ($donationStart->format('d') > $date->format('d')) {
            ++$nb;
        }

        return $nb;
    }

    private function hydrateFromRetryPayload(DonationRequest $request, string $payload): DonationRequest
    {
        try {
            $data = \GuzzleHttp\json_decode(urldecode($payload), true);
        } catch (\InvalidArgumentException $e) {
            return $request;
        }

        $data = array_filter($data);
        if (!\is_array($data) || !$data) {
            return $request;
        }

        $retry = $request->retryPayload($data);

        if ($this->validateRetryPayload($retry, $data['_retry_token'])) {
            return $retry;
        }

        return $request;
    }

    private function validateCallback(string $token): void
    {
        if ($this->getTokenManager()->isTokenValid(new CsrfToken(self::CALLBACK_TOKEN, $token))) {
            return;
        }

        throw new InvalidDonationCallbackException();
    }

    private function validateCallbackStatus(Request $request): void
    {
        if ($this->getTokenManager()->isTokenValid(new CsrfToken(self::STATUS_TOKEN, $request->query->get('_status_token')))
            && $this->isValidStatus($request->query->get('code'))) {
            return;
        }

        throw new InvalidDonationStatusException();
    }

    private function validateRetryPayload(DonationRequest $retry, string $token): bool
    {
        if ($this->getTokenManager()->isTokenValid(new CsrfToken(self::RETRY_TOKEN, $token))
        ) {
            return 0 === \count($this->getValidator()->validate($retry));
        }

        throw new InvalidDonationPayloadException();
    }

    private function isValidStatus(string $status)
    {
        return \in_array($status, self::PAYBOX_STATUSES, true);
    }

    private function getValidator(): ValidatorInterface
    {
        return $this->locator->get('validator');
    }

    private function getSession(): SessionInterface
    {
        return $this->locator->get('session');
    }

    private function getTokenManager(): CsrfTokenManagerInterface
    {
        return $this->locator->get('security.csrf.token_manager');
    }
}
