<?php

namespace AppBundle\Repository;

use AppBundle\Collection\EventRegistrationCollection;
use AppBundle\Entity\Adherent;
use AppBundle\Entity\BaseEvent;
use AppBundle\Entity\Event;
use AppBundle\Entity\EventRegistration;
use AppBundle\Entity\ReferentTag;
use AppBundle\Statistics\StatisticsParametersFilter;
use Cake\Chronos\Chronos;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\RegistryInterface;

class EventRegistrationRepository extends ServiceEntityRepository
{
    use ReferentTrait;
    use UuidEntityRepositoryTrait;

    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, EventRegistration::class);
    }

    public function findOneByUuid(string $uuid): ?EventRegistration
    {
        self::validUuid($uuid);

        return $this
            ->createQueryBuilder('r')
            ->select('r, e')
            ->leftJoin('r.event', 'e')
            ->where('r.uuid = :uuid')
            ->setParameter('uuid', $uuid)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * @param BaseEvent $event
     * @param array     $uuids
     *
     * @return EventRegistration[]
     */
    public function findByEventAndUuid(BaseEvent $event, array $uuids): array
    {
        self::validUuids($uuids);

        return $this->findBy(['event' => $event, 'uuid' => $uuids]);
    }

    public function getByEventAndUuid(BaseEvent $event, array $uuids): EventRegistrationCollection
    {
        self::validUuids($uuids);

        return $this->createEventRegistrationCollection($this->findBy(['event' => $event, 'uuid' => $uuids]));
    }

    public function findAdherentRegistration(string $eventUuid, string $adherentUuid): ?EventRegistration
    {
        self::validUuid($adherentUuid);

        return $this->createEventRegistrationQueryBuilder($eventUuid)
            ->andWhere('r.adherentUuid = :adherent_uuid')
            ->setParameter('adherent_uuid', $adherentUuid)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findGuestRegistration(string $eventUuid, string $emailAddress): ?EventRegistration
    {
        return $this->createEventRegistrationQueryBuilder($eventUuid)
            ->andWhere('r.emailAddress = :email_address')
            ->setParameter('email_address', $emailAddress)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * @param string $adherentUuid
     *
     * @return EventRegistration[]
     */
    public function findUpcomingAdherentRegistrations(string $adherentUuid): array
    {
        self::validUuid($adherentUuid);

        $registrations = $this->createAdherentEventRegistrationQueryBuilder($adherentUuid)
            ->andWhere('e.published = true')
            ->andWhere('e.beginAt >= :begin')
            // The extra 24 hours enable to include events in foreign
            // countries that are on different timezones.
            ->setParameter('begin', new \DateTime('-24 hours'))
            ->orderBy('e.beginAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $this->createEventRegistrationCollection($registrations)
            ->getUpcomingRegistrations()
            ->toArray()
        ;
    }

    /**
     * @param string $adherentUuid
     *
     * @return EventRegistration[]
     */
    public function findPastAdherentRegistrations(string $adherentUuid): array
    {
        self::validUuid($adherentUuid);

        $registrations = $this
            ->createAdherentEventRegistrationQueryBuilder($adherentUuid)
            ->andWhere('e.published = true')
            ->andWhere('e.finishAt < :finish')
            // The extra 24 hours enable to include events in foreign
            // countries that are on different timezones.
            ->setParameter('finish', new \DateTime('+24 hours'))
            ->orderBy('e.finishAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        return $this
            ->createEventRegistrationCollection($registrations)
            ->getPastRegistrations()
            ->toArray()
        ;
    }

    public function anonymizeAdherentRegistrations(Adherent $adherent): void
    {
        $qb = $this->createQueryBuilder('r');

        $qb->update()
            ->set('r.adherentUuid', $qb->expr()->literal(null))
            ->set('r.firstName', $qb->expr()->literal('Anonyme'))
            ->set('r.emailAddress', $qb->expr()->literal(null))
            ->where('r.adherentUuid = :uuid')
            ->setParameter(':uuid', $adherent->getUuid()->toString())
            ->getQuery()
            ->execute()
        ;
    }

    public function findByEvent(BaseEvent $event): EventRegistrationCollection
    {
        return $this->createEventRegistrationCollection($this->findBy(['event' => $event]));
    }

    public function findByRegisteredEmailAndEvent(string $email, BaseEvent $event): ?EventRegistration
    {
        return $this
            ->createQueryBuilder('er')
            ->where('er.emailAddress = :email')
            ->andWhere('er.event = :event')
            ->setParameter('email', $email)
            ->setParameter('event', $event)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function countEventPariticipantsInReferentManagedArea(Adherent $referent, StatisticsParametersFilter $filter = null, int $months = 5): array
    {
        $this->checkReferent($referent);

        $query = $this->createQueryBuilder('event_registrations')
            ->select('DISTINCT adherent.id, COUNT(event_registrations) AS count, YEAR_MONTH(event.beginAt) as yearmonth')
            ->join(Event::class, 'event', Join::WITH, 'event_registrations.event = event.id')
            ->leftJoin(Adherent::class, 'adherent', Join::WITH, 'adherent.uuid = event_registrations.adherentUuid')
            ->join('event.referentTags', 'tag')
            ->where('tag IN (:tags)')
            ->andWhere('event.beginAt >= :from')
            ->andWhere('event.beginAt <= :until')
            ->andWhere('event.committee IS NOT NULL')
            ->setParameter('tags', $referent->getManagedArea()->getTags())
            ->setParameter('until', (new Chronos('now'))->setTime(23, 59, 59, 999))
            ->setParameter('from', (new Chronos("first day of -$months months"))->setTime(0, 0, 0, 000))
            ->groupBy('yearmonth')
        ;

        $query = $this->addStatstFilter($filter, $query);

        return $this->aggregateCountByMonth($query->getQuery()->getArrayResult(), 'event_regiqtrations');
    }

    private function createEventRegistrationQueryBuilder(string $eventUuid): QueryBuilder
    {
        self::validUuid($eventUuid);

        return $this->createQueryBuilder('r')
            ->select('r, e')
            ->leftJoin('r.event', 'e')
            ->where('e.uuid = :event_uuid')
            ->setParameter('event_uuid', $eventUuid)
        ;
    }

    private function createAdherentEventRegistrationQueryBuilder(string $adherentUuid): QueryBuilder
    {
        return $this->createQueryBuilder('er')
            ->select('er', 'e')
            ->leftJoin('er.event', 'e')
            ->where('er.adherentUuid = :adherent')
            ->setParameter('adherent', $adherentUuid)
        ;
    }

    private function createEventRegistrationCollection(array $registrations): EventRegistrationCollection
    {
        return new EventRegistrationCollection($registrations);
    }
}
