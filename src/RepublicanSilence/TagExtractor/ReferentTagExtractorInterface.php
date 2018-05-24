<?php

namespace AppBundle\RepublicanSilence\TagExtractor;

use AppBundle\Entity\Adherent;
use Symfony\Component\HttpFoundation\Request;

interface ReferentTagExtractorInterface
{
    public const ADHERENT_TYPE_REFERENT = 0;
    public const ADHERENT_TYPE_CITIZEN_PROJECT_ADMINISTRATOR = 1; // Host
    public const ADHERENT_TYPE_COMMITTEE_ADMINISTRATOR = 2; // Supervisor or Host

    public function extractTags(Adherent $adherent, Request $request): array;
}
