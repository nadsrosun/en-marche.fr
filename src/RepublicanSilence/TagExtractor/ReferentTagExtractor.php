<?php

namespace AppBundle\RepublicanSilence\TagExtractor;

use AppBundle\Entity\Adherent;
use Symfony\Component\HttpFoundation\Request;

class ReferentTagExtractor implements ReferentTagExtractorInterface
{
    public function extractTags(Adherent $adherent, Request $request): array
    {
        return $adherent->getManagedArea()->getReferentTagCodes();
    }
}
