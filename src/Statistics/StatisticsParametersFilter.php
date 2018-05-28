<?php

namespace AppBundle\Statistics;

use AppBundle\Entity\Committee;
use AppBundle\Repository\CommitteeRepository;
use Psr\Log\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a way to handle the stats parameters.
 */
class StatisticsParametersFilter
{
    private const PARAMETER_COMMITTEE_UUID = 'committee';
    private const PARAMETER_CITY_NAME = 'city';
    private const PARAMETER_COUNTRY_CODE = 'country';

    private $committee = null;
    private $cityName = null;
    private $countryCode = null;

    public static function fromRequest(Request $request, CommitteeRepository $committeeRepository): StatisticsParametersFilter
    {
        $filter = new StatisticsParametersFilter();

        if ($uuid = $request->query->get(self::PARAMETER_COMMITTEE_UUID)) {
            if ($committee = $committeeRepository->findOneByUuid($uuid)) {
                $filter->setCommittee($committee);
            } else {
                throw new InvalidArgumentException("There is no committee with UUID '$uuid'.");
            }
        }

        if ($cityName = $request->query->get(self::PARAMETER_CITY_NAME)) {
            $filter->setCityName((string) $cityName);
        }

        if ($countryCode = $request->query->get(self::PARAMETER_COUNTRY_CODE)) {
            $filter->setCountryCode((string) $countryCode);
        }

        return $filter;
    }

    public function setCommittee(Committee $committee): void
    {
        $this->committee = $committee;
    }

    public function getCommittee(): ?Committee
    {
        return $this->committee;
    }

    public function setCityName(string $cityName): void
    {
        $this->cityName = trim($cityName);
    }

    public function getCityName(): ?string
    {
        return $this->cityName;
    }

    public function setCountryCode(string $countryCode): void
    {
        $this->countryCode = trim($countryCode);
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }
}
