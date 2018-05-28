<?php

namespace AppBundle\DataFixtures\ORM;

use AppBundle\Entity\RepublicanSilence;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

class LoadRepublicanSilenceData extends AbstractFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager)
    {
        $entity = new RepublicanSilence();
        $entity->addReferentTag($this->getReference('referent_tag_92'));
        $entity->addReferentTag($this->getReference('referent_tag_sg'));
        $entity->addReferentTag($this->getReference('referent_tag_13'));
        $entity->setBeginAt(new \DateTime('-10 days'));
        $entity->setFinishAt(new \DateTime('+10 days'));

        $manager->persist($entity);
        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            LoadReferentTagData::class,
        ];
    }
}
