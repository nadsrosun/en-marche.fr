<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity
 */
class RepublicanSilence implements \Serializable
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(type="integer", options={"unsigned"=true})
     * @ORM\GeneratedValue
     */
    private $id;

    /**
     * @var ReferentTag[]|Collection
     *
     * @ORM\ManyToMany(targetEntity="ReferentTag")
     *
     * @Assert\Count(min=1)
     */
    private $referentTags;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime")
     *
     * @Assert\NotBlank
     * @Assert\DateTime
     */
    private $beginAt;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime")
     *
     * @Assert\NotBlank
     * @Assert\DateTime
     * @Assert\Expression("value > this.getBeginAt()", message="committee.event.invalid_date_range")
     */
    private $finishAt;

    public function __construct()
    {
        $this->referentTags = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getReferentTags(): Collection
    {
        return $this->referentTags;
    }

    public function addReferentTag(ReferentTag $referentTag): void
    {
        if (!$this->referentTags->contains($referentTag)) {
            $this->referentTags->add($referentTag);
        }
    }

    public function removeReferentTag(ReferentTag $referentTag): void
    {
        $this->referentTags->removeElement($referentTag);
    }

    public function getBeginAt(): ?\DateTime
    {
        return $this->beginAt;
    }

    public function setBeginAt(\DateTime $beginAt): void
    {
        $this->beginAt = $beginAt;
    }

    public function getFinishAt(): ?\DateTime
    {
        return $this->finishAt;
    }

    public function setFinishAt(\DateTime $finishAt): void
    {
        $this->finishAt = $finishAt;
    }

    public function getReferentTagCodes(): array
    {
        return array_map(function (ReferentTag $tag) {
            return $tag->getCode();
        }, $this->referentTags->toArray());
    }

    public function serialize()
    {
        return serialize([
            $this->id,
            $this->beginAt,
            $this->finishAt,
            $this->referentTags->toArray(),
        ]);
    }

    public function unserialize($serialized)
    {
        [$this->id, $this->beginAt, $this->finishAt, $referentTags] = unserialize($serialized);

        $this->referentTags = new ArrayCollection($referentTags);
    }
}
