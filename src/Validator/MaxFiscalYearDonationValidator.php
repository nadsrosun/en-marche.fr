<?php

namespace AppBundle\Validator;

use AppBundle\Donation\DonationRequest;
use AppBundle\Donation\DonationRequestUtils;
use AppBundle\Donation\PayboxPaymentSubscription;
use AppBundle\Repository\DonationRepository;
use AppBundle\Repository\TransactionRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class MaxFiscalYearDonationValidator extends ConstraintValidator
{
    private $donationRequestUtils;
    private $donationRepository;
    private $transactionRepository;

    public function __construct(
        DonationRequestUtils $donationRequestUtils,
        DonationRepository $donationRepository,
        TransactionRepository $transactionRepository
    ) {
        $this->donationRequestUtils = $donationRequestUtils;
        $this->donationRepository = $donationRepository;
        $this->transactionRepository = $transactionRepository;
    }

    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof MaxFiscalYearDonation) {
            throw new UnexpectedTypeException($constraint, MaxFiscalYearDonation::class);
        }

        if (null === $value) {
            return;
        }

        /** @var DonationRequest $donationRequest */
        if (!($donationRequest = $this->context->getObject()) instanceof DonationRequest) {
            throw new UnexpectedTypeException($value, DonationRequest::class);
        }

        if (!$email = $donationRequest->getEmailAddress()) {
            return;
        }

        $totalCurrentAmount = $this->transactionRepository->getTotalAmountCurrentYearByEmail($email);
        $donations = $this->donationRepository->findAllSubscribedDonationByEmail($email);
        $estimateAmountRemaining = $this->donationRequestUtils->estimateAmountRemainingForSubscriptions($donations);
        $amountInCents = (int) $value * 100;
        if (PayboxPaymentSubscription::NONE !== $donationRequest->getDuration()) {
            $amountInCents = $this->donationRequestUtils->estimateAmountRemaining($amountInCents, new \DateTimeImmutable());
        }
        $maxDonationRemainingPossible = $constraint->maxDonation - $totalCurrentAmount - $estimateAmountRemaining;

        if ($maxDonationRemainingPossible - $amountInCents < 0) {
            $amountMonthly = 0;
            foreach ($donations as $donation) {
                $amountMonthly += $donation->getAmount();
            }

            $this->context
                ->buildViolation($constraint->message)
                ->setParameters([
                    '{{ total_current_amount }}' => $totalCurrentAmount / 100,
                    '{{ estimate_amount_remaining }}' => $estimateAmountRemaining / 100,
                    '{{ current_amount_monthly }}' => $amountMonthly / 100,
                    '{{ max_amount_per_fiscal_year }}' => $constraint->maxDonation / 100,
                    '{{ max_donation_remaining_possible }}' => $maxDonationRemainingPossible / 100,
                ])
                ->addViolation()
            ;
        }
    }
}
