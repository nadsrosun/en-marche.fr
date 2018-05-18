<?php

namespace Tests\AppBundle\Repository;

use AppBundle\DataFixtures\ORM\LoadDonationData;
use AppBundle\Repository\TransactionRepository;
use Tests\AppBundle\MysqlWebTestCase;
use Tests\AppBundle\TestHelperTrait;

class TransactionRepositoryTest extends MysqlWebTestCase
{
    use TestHelperTrait;

    /**
     * @var TransactionRepository
     */
    private $transactionRepository;

    public function testGetTotalAmountCurrentYearByEmail(): void
    {
        static::assertSame(25000, $this->transactionRepository->getTotalAmountCurrentYearByEmail('jacques.picard@en-marche.fr'));
    }

    protected function setUp()
    {
        parent::setUp();

        $this->loadFixtures([
            LoadDonationData::class,
        ]);

        $this->container = $this->getContainer();
        $this->transactionRepository = $this->getTransactionRepository();
    }

    protected function tearDown()
    {
        $this->loadFixtures([]);

        $this->transactionRepository = null;
        $this->container = null;

        parent::tearDown();
    }
}
