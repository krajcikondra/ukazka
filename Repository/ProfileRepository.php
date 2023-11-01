<?php

namespace App\Repository;

use App\Api\Structure\FigureParams;
use App\Entity\Profile\Profile;
use App\Entity\Profile\ProfileHasLanguage;
use App\Entity\Profile\ProfileHasService;
use App\Entity\Profile\ProfileOpeningHours;
use App\Entity\User;
use App\Model\Google\ViewPort;
use App\Utils\Strings;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;

class ProfileRepository
{

    /**
     * @return Profile[]
     */
    public function findCompanions(
        Profile $currentProfile,
        ?float $lat,
        ?float $lng,
        ?int $limit,
        ?int $offset,
        ?array $sex,
        ?int $ageFrom,
        ?int $ageTo,
        ?FigureParams $figureParams,
        ?array $ethnicity,
        ?array $orientation,
        ?array $smoker,
        ?array $serviceIds,
        ?array $languageIds,
        ?bool $isPornStar,
        ?bool $escort,
        ?bool $escortToSociety,
        ?bool $isIndependent,
        ?bool $traveling,
        ?bool $hasVideo,
        ?ViewPort $viewPort,
        string $orderBy = self::ORDER_BY_DISTANCE,
        ?bool $active = null,
        ?bool $followed = null,
        ?Profile $assignedToProfile = null,
        ?bool $approved = null,
        ?bool $bookable = null
    ) {
        $queryBuilder = $this->findQueryBuilder(
            $currentProfile,
            Profile::TYPE_COMPANION,
            $limit,
            $offset,
            null,
            null,
            $sex,
            $ageFrom,
            $ageTo,
            $figureParams,
            $ethnicity,
            $orientation,
            $smoker,
            $serviceIds,
            $languageIds,
            $isPornStar,
            $escort,
            $escortToSociety,
            null,
            null,
            null,
            false,
            false,
            null,
            (bool) $isIndependent,
            $traveling,
            $hasVideo,
            $viewPort,
            $active,
            $followed,
            null,
            $assignedToProfile,
            $approved,
            $bookable
        );

        if ($orderBy === 'alphabetAsc' || $orderBy === 'alphabetDesc') {
            $queryBuilder->innerJoin(User::class, 'u', Join::WITH, $this->getAlias() . '.userId = u.id');
        }

        $queryBuilder = $this->applyOrderBy($queryBuilder, $orderBy, $lat, $lng);

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    private function findQueryBuilder(
        ?Profile $currentProfile,
        ?string $profileType,
        ?int $limit,
        ?int $offset,
        ?float $lat,
        ?float $lng,
        ?array $sex = null,
        ?int $ageFrom = null,
        ?int $ageTo = null,
        ?FigureParams $figureParams = null,
        ?array $ethnicity = null,
        ?array $orientation = null,
        ?array $smoker = null,
        ?array $serviceIds = null,
        ?array $languageIds = null,
        ?bool $isPornStar = null,
        ?bool $escort = null,
        ?bool $escortToSociety = null,
        ?Profile $favoriteProfileByProfile = null,
        ?array $types = null,
        ?array $massageTypes = null,
        bool $isNowOpen = false,
        bool $isTodayOpen = false,
        ?bool $isFreeEntry = null,
        bool $isIndependent = false,
        ?bool $traveling = null,
        ?bool $hasVideo = null,
        ?ViewPort $viewPort = null,
        ?bool $active = null,
        ?bool $followed = null,
        ?bool $recruitment = null,
        ?Profile $assignToProfile = null,
        ?bool $approved = true,
        ?bool $bookable = null,
    ): QueryBuilder {
        $queryBuilder = $this->prepareQueryBuilderExtended($limit, $offset, null, $currentProfile, $followed, $recruitment);

        $alias = $this->getAlias();
        if ($profileType) {
            $queryBuilder
                ->andWhere($alias . '.profileType = :profileType')
                ->setParameter('profileType', $profileType);
        }

        if ($sex !== null) {
            $queryBuilder->andWhere($alias . '.sex IN(:sex)')->setParameter('sex', $sex);
        }

        $currentYear = (new \DateTime())->format('Y');
        if ($ageFrom !== null) {
            $queryBuilder->andWhere($alias . '.birthYear <= :birthYearFrom')->setParameter(':birthYearFrom', (int) $currentYear - (int) $ageFrom);
        }

        if ($ageTo !== null) {
            $queryBuilder->andWhere($alias . '.birthYear >= birthYearTo')->setParameter('birthYearTo', (int) $currentYear - (int) $ageTo);
        }

        if ($figureParams?->getEyesColor() !== null) {
            $queryBuilder->andWhere($alias . '.eyesColor IN(:eyesColor)')->setParameter('eyesColor', $figureParams->getEyesColor());
        }

        if ($figureParams?->getHairColor() !== null) {
            $queryBuilder->andWhere($alias . '.hairColor IN (:hairColor)')->setParameter('hairColor', $figureParams->getEyesColor());
        }

        if ($figureParams?->getHairLength() !== null) {
            $queryBuilder->andWhere($alias . '.hairLength IN(:hairLength)')->setParameter('hairLength', $figureParams->getHairLength());
        }

        if ($figureParams?->getBustSize() !== null) {
            $queryBuilder->andWhere($alias . '.bustSize IN(:bustSize)')->setParameter('bustSize', $figureParams->getBustSize());
        }

        if ($figureParams?->getBustType() !== null) {
            $queryBuilder->andWhere($alias . '.bustType IN(:bustType)')->setParameter('bustType', $figureParams->getBustType());
        }

        if ($figureParams?->getFigure() !== null) {
            $queryBuilder->andWhere($alias . '.figure IN(:figure)')->setParameter('figure', $figureParams->getFigure());
        }

        if ($ethnicity !== null) {
            $queryBuilder->andWhere($alias . '.ethnicity IN(:ethnicity)')->setParameter('ethnicity', $ethnicity);
        }

        if ($figureParams?->getWeightFrom() !== null) {
            $queryBuilder->andWhere($alias . '.weight >= :weightFrom')->setParameter('weightFrom', $figureParams->getWeightFrom());
        }

        if ($figureParams?->getWeightTo() !== null) {
            $queryBuilder->andWhere($alias . '.weight <= :weightTo')->setParameter('weightTo', $figureParams->getWeightTo());
        }

        if ($figureParams?->getHeightFrom() !== null) {
            $queryBuilder->andWhere($alias . '.height >= :heightFrom')->setParameter('heightFrom', $figureParams->getHeightFrom());
        }

        if ($figureParams?->getHeightTo() !== null) {
            $queryBuilder->andWhere($alias . '.height <= :heightTo')->setParameter('heightTo', $figureParams->getHeightTo());
        }

        if ($orientation !== null) {
            $queryBuilder->andWhere($alias . '.orientation IN(:orientation)')->setParameter('orientation', $orientation);
        }

        if ($smoker !== null) {
            $queryBuilder->andWhere($alias . '.smoker IN(:smoker)')->setParameter('smoker', $smoker);
        }

        if ($lat !== null) {
            $queryBuilder->andWhere($alias . '.locationLat = :lat')->setParameter('lat', $lat);
        }

        if ($lng !== null) {
            $queryBuilder->andWhere($alias . '.locationLng = :lng')->setParameter('lng', $lng);
        }

        if ($bookable !== null) {
            if ($bookable) {
                $queryBuilder->andWhere($alias . '.isBookable = TRUE');
            } else {
                $queryBuilder->andWhere($alias . '.isBookable = FALSE');
            }
        }

        if ($viewPort !== null) {
            $queryBuilder = $this->applyViewport($queryBuilder, $viewPort);
        }

        if ($serviceIds) {
            $serviceInnerQuery = $this->em->createQueryBuilder();
            $serviceSelect = $serviceInnerQuery->select('phs.profileId')
                ->from(ProfileHasService::class, 'phs')
                ->andWhere('phs.serviceId IN(:serviceIds)')
                ->groupBy('phs.profileId')
                ->having('COUNT(phs.profileId) = :serviceCount')
                ->getQuery();

            $queryBuilder->andWhere($queryBuilder->expr()->in($alias . '.id', $serviceSelect->getDQL()))
                ->setParameter('serviceIds', $serviceIds)
                ->setParameter('serviceCount', count($serviceIds));
        }

        if ($languageIds) {
            $languageInnerQuery = $this->em->createQueryBuilder();
            $languageSelect = $languageInnerQuery->select('phl.profileId')
                ->from(ProfileHasLanguage::class, 'phl')
                ->andWhere('phl.languageId IN(:languageIds)')
                ->groupBy('phl.profileId')
                ->having('COUNT(phl.profileId) = :languageCount')
                ->getQuery();

            $queryBuilder->andWhere($queryBuilder->expr()->in($alias . '.id', $languageSelect->getDQL()))
                ->setParameter('languageIds', $languageIds)
                ->setParameter('languageCount', count($languageIds));
        }

        if ($favoriteProfileByProfile) {
            $queryBuilder->innerJoin('App\Entity\Profile\ProfileHasFollowProfile', 'uhfp', Join::WITH, 'uhfp.myProfileId = ' . $alias . '.id')
                ->andWhere('uhfp.myProfileId = :favoriteProfileId')->setParameter('favoriteProfileId', $favoriteProfileByProfile->getId());
        }

        if ($isPornStar !== null) {
            $queryBuilder->andWhere($alias . '.isPornStar = :isPornStar')->setParameter('isPornStar', $isPornStar);
        }

        if ($escort !== null) {
            $queryBuilder->andWhere($alias . '.escort = :escort')->setParameter('escort', $escort);
        }

        if ($isIndependent) {
            $queryBuilder->andWhere($alias . '.isIndependent = :isIndependent')->setParameter('isIndependent', true);
        }

        if ($traveling !== null) {
            $queryBuilder->andWhere($alias . '.traveling = :traveling')->setParameter('traveling', $traveling);
        }

        if ($hasVideo !== null) {
            if ($hasVideo) {
                $queryBuilder->andWhere($alias . '.hasVideo = TRUE');
            } else {
                $queryBuilder->andWhere($alias . '.hasVideo = FALSE');
            }
        }

        if ($escortToSociety !== null) {
            $queryBuilder->andWhere($alias . '.escortToSociety = :escortToSociety')->setParameter('escortToSociety', $escortToSociety);
        }

        if ($types) {
            $queryBuilder->andWhere($alias . '.type IN(:types)')->setParameter('types', $types);
        }

        if ($active !== null) {
            $queryBuilder->andWhere($alias . '.active = :active')->setParameter('active', $active);
        }

        if ($assignToProfile !== null) {
            $queryBuilder->andWhere($alias . '.assignToProfile = :assignToProfile')
                ->setParameter('assignToProfile', $assignToProfile);
        }

        if ($massageTypes) {
            $queryBuilder->leftJoin($alias . '.massageTypes', 'mt')
                ->andWhere('mt.id IN (:massageTypeIds)')
                ->setParameter('massageTypeIds', $massageTypes);
        }

        if ($isNowOpen || $isTodayOpen) {
            $now = new \DateTime();
            $nowTime = $now->format('h:i:s');
            $expr = $this->em->getExpressionBuilder();
            $yesterday = clone $now;
            $yesterday = $yesterday->modify('-1 day');
            $todayName = Strings::lower($now->format('l'));
            $yesterdayName = Strings::lower($yesterday->format('l'));

            $queryBuilder
                ->setParameter('nowTime', $nowTime)
                ->setParameter('todayName', $todayName)
                ->setParameter('yesterdayName', $yesterdayName);

            if ($isTodayOpen) {
                $queryBuilder->andWhere($expr->in($alias . '.id', $this->em->createQueryBuilder()
                    ->select('oh.profileId')
                    ->from(ProfileOpeningHours::class, 'oh')
                    ->andWhere('oh.dayName = :todayName AND oh.isOpen = true AND ( (oh.fromHours < oh.toHours AND oh.toHours > :nowTime) OR (oh.fromHours > oh.toHours))')
                    ->orWhere('oh.dayName = :yesterdayName AND oh.isOpen = true AND oh.fromHours > oh.toHours AND oh.toHours > :nowTime')
                    ->getDQL()));
            }

            if ($isNowOpen) {
                $queryBuilder->andWhere($expr->in($alias . '.id', $this->em->createQueryBuilder()
                    ->select('ohNowOpen.profileId')
                    ->from(ProfileOpeningHours::class, 'ohNowOpen')
                    ->andWhere('ohNowOpen.dayName = :todayName AND ohNowOpen.isOpen = true AND ( (ohNowOpen.fromHours < ohNowOpen.toHours AND ohNowOpen.toHours > :nowTime AND ohNowOpen.fromHours < :nowTime) OR (ohNowOpen.fromHours > ohNowOpen.toHours AND ohNowOpen.fromHours < :nowTime))')
                    ->orWhere('ohNowOpen.dayName = :yesterdayName AND ohNowOpen.isOpen = true AND ohNowOpen.fromHours > ohNowOpen.toHours AND ohNowOpen.toHours > :nowTime')
                    ->setParameter('nowTime', $nowTime)
                    ->setParameter('todayName', $todayName)
                    ->setParameter('yesterdayName', $yesterdayName)
                    ->getDQL()));
            }
        }

        if ($isFreeEntry === true) {
            $queryBuilder->andWhere($this->getAlias() . '.enterFee IS NULL OR ' . $this->getAlias() . '.enterFee = 0');
        } elseif ($isFreeEntry === false) {
            $queryBuilder->andWhere($this->getAlias() . '.enterFee > 0');
        }


        if ($approved === true) {
            $queryBuilder->andWhere($alias . '.approved IS NOT NULL');
        } elseif ($approved === false) {
            $queryBuilder->andWhere($alias . '.approved IS NULL');
        }
        return $queryBuilder;
    }
}