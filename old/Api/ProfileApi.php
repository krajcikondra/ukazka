<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Dto\Profile\GetCompanionProfilePreviewListParams;
use App\Api\Structure\PageResult;
use App\Entity\Profile\Profile;

final class ProfileApi
{
    public function getCompanionProfilePreviewList(
        Profile $currentProfile,
        GetCompanionProfilePreviewListParams $params,
    ): PageResult {
        $showAssignedToMyProfile = $currentProfile->getId() === $params->getAssignedToProfile()?->getId();
        $approved = $showAssignedToMyProfile ? null : true;
        $totalCount = $this->profileRepository->findCompanionCount($currentProfile, $params, $approved);

        $viewPort = $params->getViewPort();
        if ($totalCount <= 0) {
            if ($viewPort === null) {
                return new PageResult([], 0, $params->getLimit());
            }
            $viewPort->increase();
            $totalCount = $this->profileRepository->findCompanionCount($currentProfile, $params, $approved);

            if ($totalCount <= 0) {
                return new PageResult([], $totalCount, $params->getOffset());
            }
        }

        $profiles = $this->profileRepository->findCompanions(
            $currentProfile,
            $params->getLat(),
            $params->getLng(),
            $params->getLimit(),
            $params->getOffset(),
            $params->getSex(),
            $params->getAgeFrom(),
            $params->getAgeTo(),
            $params->getFigureParams(),
            $params->getEthnicity(),
            $params->getOrientation(),
            $params->getSmoker(),
            $params->getServiceIds(),
            $params->getLanguageIds(),
            $params->getIsPornStar(),
            $params->getEscort(),
            $params->getEscortToSociety(),
            $params->getIsIndependent(),
            $params->getTraveling(),
            $params->getHasVideo(),
            $params->getViewPort(),
            $params->getOrderBy(),
            true,
            $params->getFollowed(),
            $params->getAssignedToProfile(),
            $approved,
            $params->getBookable()
        );

        $data = [];
        foreach ($profiles as $profile) {
            $data[] = $this->getProfileCompanionListPreviewData(
                $profile,
                $currentProfile,
                $params->getLat(),
                $params->getLng(),
            );
        }

        return new PageResult($data, $totalCount, $params->getLimit());
    }
}