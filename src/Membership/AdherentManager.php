<?php

namespace AppBundle\Membership;

use AppBundle\Entity\Adherent;
use AppBundle\Entity\AdherentActivationToken;
use AppBundle\Repository\AdherentRepository;
use Cake\Chronos\Chronos;
use Doctrine\Common\Persistence\ObjectManager;

class AdherentManager
{
    private $manager;
    private $repository;

    public function __construct(
        AdherentRepository $repository,
        ObjectManager $manager
    ) {
        $this->repository = $repository;
        $this->manager = $manager;
    }

    public function activateAccount(Adherent $adherent, AdherentActivationToken $token, bool $flush = true)
    {
        $adherent->activate($token);

        if ($flush) {
            $this->manager->flush();
        }
    }

    public function countActiveAdherents(): int
    {
        return $this->repository->countActiveAdherents();
    }

    public function countMembersByMonthManagedBy(Adherent $referent, int $months = 6): array
    {
        foreach (range(0, $months - 1) as $monthInterval) {
            $until = $monthInterval
                        ? (new Chronos("last day of -$monthInterval month"))->setTime(23, 59, 59, 999)
                        : new Chronos()
            ;

            $count = $this->repository->countMembersManagedBy($referent, $until);

            $countByMonth[$until->format('Y-m')] = ['total' => $count];
        }

        return $countByMonth;
    }
}
