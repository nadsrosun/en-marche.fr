<?php

namespace AppBundle\RepublicanSilence\TagExtractor;

use AppBundle\Entity\Adherent;
use AppBundle\Entity\CitizenProjectMembership;
use AppBundle\Entity\PostAddress;
use Symfony\Component\HttpFoundation\Request;

class CitizenProjectReferentTagExtractor implements ReferentTagExtractorInterface
{
    public function extractTags(Adherent $adherent, Request $request): array
    {
        if (!$request->attributes->has('slug') && !$request->attributes->has('project_slug')) {
            return [];
        }

        $citizenProjectSlug = $request->attributes->get('slug', $request->attributes->get('project_slug'));

        /** @var CitizenProjectMembership $membership */
        foreach ($adherent->getCitizenProjectMemberships()->getCitizenProjectAdministratorMemberships() as $membership) {
            $citizenProject = $membership->getCitizenProject();

            if ($citizenProject->getSlug() === $citizenProjectSlug) {
                $tags = [
                    $citizenProject->getPostalCode(),
                    $citizenProject->getCityName(),
                    $citizenProject->getCountryName(),
                    $citizenProject->getCountry(),
                ];

                if (PostAddress::FRANCE === $citizenProject->getCountry()) {
                    $tags[] = \mb_substr($citizenProject->getPostalCode(), 0, 2);
                }

                return $tags;
            }
        }

        return [];
    }
}
