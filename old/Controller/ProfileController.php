<?php

declare(strict_types=1);

namespace App\Controller;

use App\Api\Dto\Profile\Factory\GetCompanionProfilePreviewListFactory;
use App\Api\Profile\ProfileReportApi;
use App\Api\ProfileApi;
use App\Model\Auth\AuthService;
use App\Model\GeoIpLocator;
use App\Model\Google\PlaceApiClient;
use App\Model\Profile\ProfileActivityChecker;
use App\Model\Profile\Validator\ProfileValidator;
use App\Model\Queue\Producer\LowPriorityProducer;
use App\Repository\ProfileRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Cache\CacheInterface;


/**
 * @Route("/api/profile", name="profile_api")
 */
final class ProfileController extends BaseController
{
    public function __construct(
        private LoggerInterface $logger,
        private ProfileRepository $profileRepository,
        private ProfileApi $profileApi,
        private PlaceApiClient $placeApiClient,
        private GeoIpLocator $geoIpLocator,
        private CacheInterface $cacheApp,
        private LowPriorityProducer $lowPriorityProducer,
        private ProfileActivityChecker $profileActivityChecker,
        private ProfileValidator $profileValidator,
        private ProfileReportApi $profileReportApi,
        private AuthService $authService,
        private GetCompanionProfilePreviewListFactory $companionProfilePreviewListFactory,
    ) {
        parent::__construct(
            $logger,
            $profileRepository,
            $placeApiClient,
            $geoIpLocator,
            $cacheApp,
            $lowPriorityProducer,
            $profileActivityChecker,
            $authService
        );
    }

    /**
     * @Route("/companion", name="getCompanion", methods={"GET"})
     */
    public function getCompanionProfilePreviewList(Request $request, Security $security): JsonResponse
    {
        try {
            $currentProfile = $this->getProfileOrCrash($security, $request);
            $params = $this->companionProfilePreviewListFactory->createDto($request);

            $data = $this->profileApi->getCompanionProfilePreviewList(
                $currentProfile,
                $params,
            );

            $data = $data->toArray();
            return new JsonResponse($data);
        } catch (\Throwable $e) {
            return $this->getJsonErrorByException($e);
        }
    }

    /**
     * @Route("/save-companion-profile", name="saveCompanionProfile", methods={"POST"})
     */
    public function saveCompanionProfile(Request $request, Security $security): ?JsonResponse
    {
        try {
            $values = $this->getPostValues($request);
            $data = $this->profileApi->saveCompanionProfile(
                $values,
                $this->getCurrentUser($security, $request),
            );
            return new JsonResponse($data);
        } catch (\Throwable $e) {
            return $this->getJsonErrorByException($e);
        }
    }
}
