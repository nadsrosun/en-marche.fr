<?php

namespace AppBundle\Repository;

use AppBundle\Entity\Adherent;
use AppBundle\Statistics\StatisticsParametersFilter;
use Cake\Chronos\Chronos;
use Doctrine\ORM\QueryBuilder;

trait ReferentTrait
{
    private function checkReferent(Adherent $referent): void
    {
        if (!$referent->isReferent()) {
            throw new \InvalidArgumentException('Adherent must be a referent.');
        }
    }

    protected function aggregateCountByMonth(array $itemsCount, string $type, int $months = 6): array
    {
        foreach (range(0, $months - 1) as $month) {
            $until = (new Chronos("first day of -$month month"));
            $countByMonth[$until->format('Y-m')][$type] = 0;
            foreach ($itemsCount as $count) {
                if ($until->format('Ym') === $count['yearmonth']) {
                    $countByMonth[$until->format('Y-m')][$type] = (int) $count['count'];
                }
            }
        }

        return $countByMonth;
    }

    private function addStatstFilter(StatisticsParametersFilter $filter, QueryBuilder $query) : QueryBuilder
    {
        if ($filter->getCommittee()) {
            $query->andWhere('event.committee = :committee')
                ->setParameter('committee', $filter->getCommittee())
            ;
        }

        if ($filter->getCityName()) {
            $query->andWhere('event.postAddress.cityName = :city')
                ->setParameter('city', $filter->getCityName())
            ;
        }

        if ($filter->getCountryCode()) {
            $query->andWhere('event.postAddress.country = :country')
                ->setParameter('country', $filter->getCountryCode())
            ;
        }

        return $query;
    }
}
