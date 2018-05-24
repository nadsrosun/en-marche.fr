<?php

namespace AppBundle\RepublicanSilence\TagExtractor;

use AppBundle\Entity\Adherent;
use AppBundle\Entity\CommitteeMembership;
use Symfony\Component\HttpFoundation\Request;

class CommitteeReferentTagExtractor implements ReferentTagExtractorInterface
{
    public function extractTags(Adherent $adherent, Request $request): array
    {
        if (!$request->attributes->has('slug')) {
            return [];
        }

        $committeeSlug = $request->attributes->get('slug');

        /** @var CommitteeMembership $membership */
        foreach ($adherent->getMemberships()->getCommitteeHostMemberships() as $membership) {
            $committee = $membership->getCommittee();
            if ($committee->getSlug() === $committeeSlug) {
                return $committee->getReferentTagsCodes();
            }
        }

        return [];
    }
}
