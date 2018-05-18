<?php

namespace Tests\AppBundle\Validator;

use AppBundle\Donation\DonationRequest;
use AppBundle\Donation\DonationRequestUtils;
use AppBundle\Donation\PayboxPaymentSubscription;
use AppBundle\Entity\Donation;
use AppBundle\Entity\PostAddress;
use AppBundle\Membership\MembershipRegistrationProcess;
use AppBundle\Repository\DonationRepository;
use AppBundle\Repository\TransactionRepository;
use AppBundle\Validator\MaxFiscalYearDonation;
use AppBundle\Validator\MaxFiscalYearDonationValidator;
use Cocur\Slugify\Slugify;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class MaxFiscalYearDonationValidatorTest extends ConstraintValidatorTestCase
{
    /**
     * @var DonationRequestUtils
     */
    private $donationRequestUtils;

    /**
     * @dataProvider noValidateDonationProvider
     */
    public function testNoValidation(
        DonationRequest $donationRequest,
        ?int $value
    ): void {
        $this->setObject($donationRequest);

        $this->validator->validate($value, new MaxFiscalYearDonation());

        $this->assertNoViolation();
    }

    public function noValidateDonationProvider(): iterable
    {
        yield 'No validation if no value' => [
            $this->createDonationRequest(),
            null,
        ];
        yield 'No validation if no email' => [
            $this->createDonationRequest(PayboxPaymentSubscription::NONE, null),
            50,
        ];
    }

    /**
     * @dataProvider donationProvider
     */
    public function testValidateWithNoError(
        DonationRequest $donationRequest,
        ?int $value,
        int $maxDonation,
        int $totalCurrentAmount = 0,
        array $subscriptions = []
    ): void {
        $this->setObject($donationRequest);
        $this->validator = $this->createCustomValidatorSuccess($totalCurrentAmount, $subscriptions);
        $this->validator->initialize($this->context);

        $this->validator->validate($value, new MaxFiscalYearDonation(['maxDonation' => $maxDonation]));

        $this->assertNoViolation();
    }

    public function donationProvider(): iterable
    {
        yield 'No violation with no subscription 0 total donation' => [
            $this->createDonationRequest(PayboxPaymentSubscription::NONE),
            50,
            7500 * 100,
        ];
        yield 'No violation with no subscription max possible donation' => [
            $this->createDonationRequest(PayboxPaymentSubscription::NONE),
            50,
            7500 * 100,
            7450 * 100,
        ];
        yield 'No violation with subscription 0 total donation' => [
            $this->createDonationRequest(PayboxPaymentSubscription::NONE),
            50,
            7500 * 100,
            0,
            [$this->createSubscription(5000, '2018/06/01')],
        ];
        yield 'No violation with subscription max possible donation' => [
            $this->createDonationRequest(PayboxPaymentSubscription::NONE),
            50,
            7500 * 100,
            7150 * 100,
            [$this->createSubscription(5000, '2018/06/01')],
        ];
    }

    /**
     * @dataProvider donationFailProvider
     */
    public function testValidateWithError(
        array $parameters,
        DonationRequest $donationRequest,
        ?int $value,
        int $maxDonation,
        int $totalCurrentAmount = 0,
        array $subscriptions = []
    ): void {
        $this->setObject($donationRequest);
        $this->validator = $this->createCustomValidatorSuccess($totalCurrentAmount, $subscriptions);
        $this->validator->initialize($this->context);

        $this->validator->validate($value, new MaxFiscalYearDonation(['maxDonation' => $maxDonation]));

        $this
            ->buildViolation('donation.max_fiscal_year_donation')
            ->setParameters($parameters)
            ->assertRaised()
        ;
    }

    public function donationFailProvider(): iterable
    {
        yield 'Violation with no subscription 0 total donation' => [
            [
                '{{ total_current_amount }}' => 0,
                '{{ estimate_amount_remaining }}' => 0,
                '{{ current_amount_monthly }}' => 0,
                '{{ max_amount_per_fiscal_year }}' => 7500,
                '{{ max_donation_remaining_possible }}' => 7500,
            ],
            $this->createDonationRequest(PayboxPaymentSubscription::NONE),
            8000,
            7500 * 100,
        ];
        yield 'Violation with no subscription max possible donation' => [
            [
                '{{ total_current_amount }}' => 7500,
                '{{ estimate_amount_remaining }}' => 0,
                '{{ current_amount_monthly }}' => 0,
                '{{ max_amount_per_fiscal_year }}' => 7500,
                '{{ max_donation_remaining_possible }}' => 0,
            ],
            $this->createDonationRequest(PayboxPaymentSubscription::NONE),
            50,
            7500 * 100,
            7500 * 100,
        ];
        yield 'Violation with subscription 0 total donation' => [
            [
                '{{ total_current_amount }}' => 0,
                '{{ estimate_amount_remaining }}' => 6000,
                '{{ current_amount_monthly }}' => 1000,
                '{{ max_amount_per_fiscal_year }}' => 7500,
                '{{ max_donation_remaining_possible }}' => 1500,
            ],
            $this->createDonationRequest(PayboxPaymentSubscription::NONE),
            4000,
            7500 * 100,
            0,
            [$this->createSubscription(100000, '2018/06/01')],
        ];
        yield 'Violation with subscription max possible donation' => [
            [
                '{{ total_current_amount }}' => 7200,
                '{{ estimate_amount_remaining }}' => 300,
                '{{ current_amount_monthly }}' => 50,
                '{{ max_amount_per_fiscal_year }}' => 7500,
                '{{ max_donation_remaining_possible }}' => 0,
            ],
            $this->createDonationRequest(PayboxPaymentSubscription::NONE),
            50,
            7500 * 100,
            7200 * 100,
            [$this->createSubscription(5000, '2018/06/01')],
        ];
        yield 'Violation for new subscription' => [
            [
                '{{ total_current_amount }}' => 0,
                '{{ estimate_amount_remaining }}' => 0,
                '{{ current_amount_monthly }}' => 0,
                '{{ max_amount_per_fiscal_year }}' => 7500,
                '{{ max_donation_remaining_possible }}' => 7500,
            ],
            $this->createDonationRequest(PayboxPaymentSubscription::UNLIMITED),
            5000,
            7500 * 100,
            0,
        ];
    }

    protected function createValidator()
    {
        return $this->createCustomValidatorFail();
    }

    protected function createCustomValidatorFail()
    {
        $donationRepository = $this->createMock(DonationRepository::class);
        $transactionRepository = $this->createMock(TransactionRepository::class);

        $transactionRepository->expects($this->never())
            ->method('getTotalAmountCurrentYearByEmail')
        ;
        $donationRepository->expects($this->never())
            ->method('findAllSubscribedDonationByEmail')
        ;

        return new MaxFiscalYearDonationValidator(
            $this->createDonationRequestUtils(),
            $donationRepository,
            $transactionRepository
        );
    }

    protected function createCustomValidatorSuccess(int $totalCurrentAmount = 0, array $subscriptions = []): MaxFiscalYearDonationValidator
    {
        $donationRepository = $this->createMock(DonationRepository::class);
        $transactionRepository = $this->createMock(TransactionRepository::class);

        $transactionRepository->expects($this->once())
            ->method('getTotalAmountCurrentYearByEmail')
            ->willReturn($totalCurrentAmount)
        ;
        $donationRepository->expects($this->once())
            ->method('findAllSubscribedDonationByEmail')
            ->willReturn($subscriptions)
        ;

        return new MaxFiscalYearDonationValidator(
            $this->createDonationRequestUtils(),
            $donationRepository,
            $transactionRepository
        );
    }

    private function createDonationRequestUtils(): DonationRequestUtils
    {
        $mock = $this->createMock(DonationRequestUtils::class);
        $mock->method('estimateAmountRemainingForSubscriptions')->willReturnCallback(function (array $donations) {
            return $this->donationRequestUtils->estimateAmountRemainingForSubscriptions(
                $donations,
                \DateTimeImmutable::createFromFormat('Y/m/d', '2018/06/15')
            );
        });
        $mock->method('estimateAmountRemaining')->willReturnCallback(function (int $amount) {
            return $this->donationRequestUtils->estimateAmountRemaining(
                $amount,
                \DateTimeImmutable::createFromFormat('Y/m/d', '2018/06/15'),
                \DateTimeImmutable::createFromFormat('Y/m/d', '2018/06/15')
            );
        });

        return $mock;
    }

    private function createDonationRequest(
        int $duration = PayboxPaymentSubscription::NONE,
        ?string $email = 'test@test.test'
    ): DonationRequest {
        $donationRequest = new DonationRequest(Uuid::uuid4(), '123.0.0.1', 50., $duration);
        $donationRequest->setEmailAddress($email);

        return $donationRequest;
    }

    private function createSubscription(int $amount, string $createdAt): Donation
    {
        $subscription = new Donation(
            Uuid::uuid4(),
            'ref',
            $amount,
            'm',
            'Jean',
            'Dupont',
            'test@test.test',
            $this->createMock(PostAddress::class),
            null,
            '127.0.0.1',
            PayboxPaymentSubscription::UNLIMITED
        );

        $reflectObj = new \ReflectionObject($subscription);
        $refProp = $reflectObj->getProperty('createdAt');

        $refProp->setAccessible(true);
        $refProp->setValue($subscription, \DateTimeImmutable::createFromFormat('Y/m/d', $createdAt));
        $refProp->setAccessible(false);

        return $subscription;
    }

    protected function setUp()
    {
        parent::setUp();

        $this->donationRequestUtils = new DonationRequestUtils(
            $this->createMock(ServiceLocator::class),
            $this->createMock(Slugify::class),
            $this->createMock(MembershipRegistrationProcess::class)
        );
    }
}
