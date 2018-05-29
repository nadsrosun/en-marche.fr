<?php

namespace AppBundle\Repository;

use AppBundle\Entity\Adherent;
use AppBundle\Entity\Reporting\CommitteeMembershipAction;
use AppBundle\Entity\Reporting\CommitteeMembershipHistory;
use Cake\Chronos\Chronos;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Ramsey\Uuid\UuidInterface;

class CommitteeMembershipHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommitteeMembershipHistory::class);
    }

    public function countAdherentMemberOfAtLeastOneCommitteeManagedBy(Adherent $referent, \DateTimeInterface $until): int
    {
        if (!$referent->isReferent()) {
            throw new \InvalidArgumentException('Adherent must be a referent.');
        }

        $query = $this->createQueryBuilder('history')
            ->select('history.action, history.adherentUuid, COUNT(history) AS count')
            ->join('history.referentTags', 'tags')
            ->where('tags IN (:tags)')
            ->andWhere('history.date <= :until')
            ->groupBy('history.action, history.adherentUuid')

            ->setParameter('tags', $referent->getManagedArea()->getTags())
            ->setParameter('until', $until)
            ->getQuery()
        ;

        // Let's cache past data as they are never going to change
        $firstDayOfMonth = (new Chronos('first day of this month'))->setTime(0, 0);
        if ($firstDayOfMonth > $until) {
            $query->useResultCache(true, 5184000); // 60 days
        }

        $results = $query->getArrayResult();
        $countByAdherent = [];

        /** @var UuidInterface $uuid */
        foreach ($results as ['count' => $count, 'action' => $action, 'adherentUuid' => $uuid]) {
            $uuid = $uuid->toString();

            if (CommitteeMembershipAction::LEAVE === $action) {
                $countByAdherent[$uuid] = ($countByAdherent[$uuid] ?? 0) - $count;
            } elseif (CommitteeMembershipAction::JOIN === $action) {
                $countByAdherent[$uuid] = ($countByAdherent[$uuid] ?? 0) + $count;
            } else {
                throw new \RuntimeException("'$action' is not handled");
            }
        }

        return count(array_filter($countByAdherent));
    }
}
