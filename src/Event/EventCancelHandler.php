<?php

namespace AppBundle\Event;

use AppBundle\CitizenAction\CitizenActionEvent;
use AppBundle\Entity\BaseEvent;
use AppBundle\Entity\Event as CommitteeEvent;
use AppBundle\Events;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class EventCancelHandler
{
    private $dispatcher;
    private $manager;

    public function __construct(EventDispatcherInterface $dispatcher, ObjectManager $manager)
    {
        $this->dispatcher = $dispatcher;
        $this->manager = $manager;
    }

    public function handle(BaseEvent $event): BaseEvent
    {
        $event->cancel();

        $this->manager->flush();

        $this->dispatcher->dispatch(
            $this->getEventType($event),
            $this->createDispatchedEvent($event)
        );

        return $event;
    }

    private function getEventType(BaseEvent $event): string
    {
        return $event instanceof CommitteeEvent ? Events::EVENT_CANCELLED : Events::CITIZEN_ACTION_CANCELLED;
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
