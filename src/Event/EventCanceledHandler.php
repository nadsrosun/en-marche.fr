<?php

namespace AppBundle\Event;

use AppBundle\CitizenAction\CitizenActionEvent;
use AppBundle\Entity\BaseEvent;
use AppBundle\Entity\CitizenAction;
use AppBundle\Entity\Event as CommitteeEvent;
use AppBundle\Events;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class EventCanceledHandler
{
    private $dispatcher;
    private $manager;

    private const EVENTS_MAPPING = [
        CommitteeEvent::class => Events::EVENT_CANCELLED,
        CitizenAction::class => Events::CITIZEN_ACTION_CANCELLED,
    ];

    public function __construct(EventDispatcherInterface $dispatcher, ObjectManager $manager)
    {
        $this->dispatcher = $dispatcher;
        $this->manager = $manager;
    }

    public function handle(BaseEvent $event): BaseEvent
    {
        $className = get_class($event);

        if (!array_key_exists($className, self::EVENTS_MAPPING)) {
            throw new \InvalidArgumentException(sprintf('[%s] Invalid Event type [%s]', self::class, $className));
        }

        $event->cancel();

        $this->manager->flush();

        $this->dispatcher->dispatch(
            self::EVENTS_MAPPING[$className],
            $this->createDispatchedEvent($event)
        );

        return $event;
    }

    private function createDispatchedEvent(BaseEvent $event): Event
    {
        if ($event instanceof CommitteeEvent) {
            return new EventEvent(
                $event->getOrganizer(),
                $event,
                $event->getCommittee()
            );
        }

        return new CitizenActionEvent($event, $event->getOrganizer());
    }
}
