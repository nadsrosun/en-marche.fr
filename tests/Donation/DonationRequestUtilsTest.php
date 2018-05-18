<?php

namespace Tests\AppBundle\Donation;

use AppBundle\Donation\DonationRequestUtils;
use AppBundle\Donation\PayboxPaymentSubscription;
use AppBundle\Entity\Donation;
use AppBundle\Entity\PostAddress;
use AppBundle\Membership\MembershipRegistrationProcess;
use Cocur\Slugify\Slugify;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ServiceLocator;

class DonationRequestUtilsTest extends TestCase
{
    /**
     * @var DonationRequestUtils
     */
    private $donationRequestUtils;

    public function donationsProvider(): iterable
    {
        yield 'Date in month past' => [
            [$this->createDonationWithCustomCreationDate(static::createImmutableDate('2018/01/01'))],
            static::createImmutableDate('2018/06/15'),
            60,
        ];
        yield 'Date in month in coming' => [
            [$this->createDonationWithCustomCreationDate(static::createImmutableDate('2018/01/15'))],
            static::createImmutableDate('2018/06/01'),
            70,
        ];
        yield 'Date in month past at end of year' => [
            [$this->createDonationWithCustomCreationDate(static::createImmutableDate('2018/12/01'))],
            static::createImmutableDate('2018/12/15'),
            0,
        ];
        yield 'Date in month in coming at end of year' => [
            [$this->createDonationWithCustomCreationDate(static::createImmutableDate('2018/12/15'))],
            static::createImmutableDate('2018/12/01'),
            10,
        ];
    }

    /**
     * @dataProvider donationsProvider
     */
    public function testEstimateAmountRemainingForSubscriptions(
        array $donations,
        \DateTimeInterface $date,
        int $expected
    ): void {
        static::assertSame(
            $expected,
            $this->donationRequestUtils->estimateAmountRemainingForSubscriptions($donations, $date)
        );
    }

    public function donationProvider(): iterable
    {
        yield 'Date in month past' => [
            static::createImmutableDate('2018/01/01'),
            static::createImmutableDate('2018/06/15'),
            6,
        ];
        yield 'Date in month in coming' => [
            static::createImmutableDate('2018/01/15'),
            static::createImmutableDate('2018/06/01'),
            7,
        ];
        yield 'Date in month past at end of year' => [
            static::createImmutableDate('2018/12/01'),
            static::createImmutableDate('2018/12/15'),
            0,
        ];
        yield 'Date in month in coming at end of year' => [
            static::createImmutableDate('2018/12/15'),
            static::createImmutableDate('2018/12/01'),
            1,
        ];
    }

    /**
     * @dataProvider donationProvider
     */
    public function testEstimateNbIterationBeforeNextFiscalYear(
        \DateTimeInterface $donationStart,
        \DateTimeInterface $date,
        int $expected
    ): void {
        static::assertSame(
            $expected,
            $this->donationRequestUtils->estimateNbIterationBeforeNextFiscalYear($donationStart, $date)
        );
    }

    protected function setUp()
    {
        $this->donationRequestUtils = $this->getDonationRequestUtils();
    }

    protected function tearDown()
    {
        $this->donationRequestUtils = null;
    }

    private function getDonationRequestUtils(): DonationRequestUtils
    {
        return new DonationRequestUtils(
            $this->createMock(ServiceLocator::class),
            $this->createMock(Slugify::class),
            $this->createMock(MembershipRegistrationProcess::class)
        );
    }

    private function createDonationWithCustomCreationDate(\DateTimeImmutable $date): Donation
    {
        $donation = new Donation(
            Uuid::uuid4(),
            '10',
            '10',
            'male',
            'jean',
            'dupont',
            'jp@j.p',
            $this->createMock(PostAddress::class),
            null,
            '127.0.0.1',
            PayboxPaymentSubscription::UNLIMITED
        );

        $reflectObject = new \ReflectionObject($donation);
        $reflectProp = $reflectObject->getProperty('createdAt');
        $reflectProp->setAccessible(true);
        $reflectProp->setValue($donation, $date);
        $reflectProp->setAccessible(false);

        return $donation;
    }

    private static function createImmutableDate(string $date): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromFormat('Y/m/d', $date);
    }
}
