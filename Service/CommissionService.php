<?php

namespace App\Service\Finance;

use App\Entity\GlobalSettings;
use App\Entity\User;
use App\Entity\UserSettings;
use App\Helper\SecurityHelper;
use App\Repository\UserRepository;
use App\Repository\UserSettingsRepository;
use App\Service\GlobalSettingsService;
use App\Service\User\Admin\LoggerService;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\GlobalSettingsRepository;
use Exception;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bridge\Doctrine\Logger\DbalLogger;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class CommissionService
{
	const CACHE_EXPIRED_TIME = 36000000;
	private EntityManagerInterface $manager;
    private GlobalSettingsService $globalSettingsService;
    private UserSettingsRepository $userSettingsRepository;
    private UserRepository $userRepository;
    private LoggerService $loggerService;
    private TokenStorageInterface $tokenStorage;
    private User $adminUser;
	private CacheInterface $commissionsCache;

    public function __construct(
        EntityManagerInterface $manager,
        GlobalSettingsService $globalSettingsService,
        UserSettingsRepository $userSettingsRepository,
        UserRepository $userRepository,
        LoggerService $loggerService,
        TokenStorageInterface $tokenStorage,
        CacheInterface $commissionsCache
    )
    {
        $this->manager = $manager;
        $this->globalSettingsService = $globalSettingsService;
        $this->userSettingsRepository = $userSettingsRepository;
        $this->userRepository = $userRepository;
        $this->loggerService = $loggerService;
		$this->commissionsCache = $commissionsCache;

        if (is_object($tokenStorage->getToken())) {
            $this->adminUser = $tokenStorage->getToken()->getUser();
        }
    }

	/**
	 * @return array
	 * @throws InvalidArgumentException
	 */
    public function getCommissions(): ?array
    {
	    $data = $this->commissionsCache->get('commissions',
		    function (ItemInterface $item) {
			    $commissions = $this->globalSettingsService->get('commissions');

			    if (empty($commissions)) {
				    $commissions = null;
			    }

			    $item->expiresAfter(self::CACHE_EXPIRED_TIME);
			    return $commissions;
		    });

        return $this->_mapToFront($data);
    }

	/**
	 * @param Request $request
	 * @return array
	 * @throws Exception
	 * @throws InvalidArgumentException
	 */
    public function setCommissions(Request $request): array
    {
        $data = $this->_mapToDb($request->request->all('data'));
        $this->_changeUsersCommissions($data);

        $this->globalSettingsService->set('commissions', $data);

		$this->commissionsCache->delete('commissions');

        $this->loggerService->log(
            'Изменение комиссии',
            'Общая комиссия была изменена',
        );

        return ['Status' => 'OK'];
    }

    /**
     * @param int $userId
     * @return array
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function getUserCommissions(int $userId): array
    {
	    $commissions = $this->commissionsCache->get("commissions_{$userId}",
		    function (ItemInterface $item ) use ($userId) {
			    $commissions = $this->_getUserSettings($userId)->getCommissions();

			    if (empty($commissions)) {
				    $commissions = null;
			    }

			    $item->expiresAfter(self::CACHE_EXPIRED_TIME);
			    return $commissions;
		    });

        return ["data" => $this->_mapToFront($commissions)];
    }

    /**
     * @param int $userId
     * @return array
     * @throws Exception|InvalidArgumentException
     */
    public function getUserCommissionsOrGlobal(int $userId): array
    {
		$commissions = $this->commissionsCache->get("commissions_{$userId}",
		    function (ItemInterface $item ) use ($userId) {
			    $userSettings = $this->_getUserSettings($userId);

			    $item->expiresAfter(self::CACHE_EXPIRED_TIME);

			    if ($userSettings !== null && !empty($userSettings->getCommissions())) {
				    return $userSettings->getCommissions();
			    } else {
					return null;
			    }
		    });

		if ($commissions !== null) {
			return $this->_mapToFront($commissions);
		}

        return $this->getCommissions();
    }

    /**
     * @param Request $request
     * @param int $userId
     * @return array
     * @throws Exception|InvalidArgumentException
     */
    public function setUserCommissions(Request $request, int $userId): array
    {
        $data = $request->request->all('data');
        $userSettings = $this->_getUserSettings($userId);

        if(!$userSettings){
            return ["Error" => 'User settings not found!'];
        }

        $userSettings->setCommissions($this->_mapToDb($data));

        $this->manager->persist($userSettings);
        $this->manager->flush();

		$this->commissionsCache->delete("commissions_{$userId}");

        $this->loggerService->log(
            'Изменение комиссии юзера',
            'Комиссия юзера ' . $userSettings->getUser()->getEmail() . ' была изменена',
            $userSettings->getUser()
        );

        return ["Status" => "OK"];
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getExceptionUsers(): array
    {
        $result = [];

        $usersCommissions = $this->userSettingsRepository
            ->createQueryBuilder('uc')
            ->where("uc.commissions != '[]'")
            ->getQuery()
            ->getResult();

        foreach($usersCommissions as $userCommission){
            $result[] = [
                'user_id' => $userCommission->getUser()->getId(),
                'email' => $userCommission->getUser()->getEmail(),
                'commissions' => $this->_mapToFront($userCommission->getCommissions())
            ];
        }

        return $result;
    }

    /**
     * @param Request $request
     * @return array
     * @throws Exception|InvalidArgumentException
     */
    public function resetUserCommissions(Request $request): array
    {
		$userId = (int)$request->request->get('user_id');
        $userSettings = $this->_getUserSettings($userId);
        if($userSettings){
            $userSettings->setCommissions(null);

            $this->manager->persist($userSettings);
            $this->manager->flush();

			$this->commissionsCache->delete("commissions_{$userId}");

            $this->loggerService->log(
                'Сброс комиссии юзера',
                'Комиссия юзера ' . $userSettings->getUser()->getEmail() . ' была сброшена',
                $userSettings->getUser()
            );

            return ['Status' => 'OK'];
        }

        return ['Error' => 'User settings not found!'];
    }

    /**
     * @param int $userId
     * @return UserSettings|null
    */
    private function _getUserSettings(int $userId): ?UserSettings
    {
        $user = $this->userRepository->find($userId);
        if(!$user){
            return null;
        }

        return $this->userSettingsRepository->findOneBy(['user' => $user]);
    }

    /**
     * @param array $data
     * @return array
     */
    private function _mapToDb(array $data): array
    {
        $transfer = $data['transfer'];

        return [
            'transfer' => [
                'USD' => $transfer['USD']['SW'],
                'EUR' => $transfer['EUR']['SW'],
                'BTC' => $transfer['BTC']['BTC'],
                'ETH' => $transfer['ETH']['ERC20'],
                'ERC20' => $transfer['USDT']['ERC20'],
                'TRC20' => $transfer['USDT']['TRC20'],
                'BEP20' => $transfer['USDT']['BEP20'],
                'TRX' => $transfer['TRX']['TRC20'],
                'BNB' => $transfer['BNB']['BEP20'],
                'SWP' => $transfer['SWP']['BEP20'],
                'SWCT' => $transfer['SWCT']['BEP20'],
            ],
            'withdraw' => [
                'USD' => $transfer['USD']['PM'],
                'EUR' => $transfer['EUR']['PM'],
                'BTC' => $transfer['BTC']['BTC'],
                'ETH' => $transfer['ETH']['ERC20'],
                'ERC20' => $transfer['USDT']['ERC20'],
                'TRC20' => $transfer['USDT']['TRC20'],
                'BEP20' => $transfer['USDT']['BEP20'],
                'TRX' => $transfer['TRX']['TRC20'],
                'BNB' => $transfer['BNB']['BEP20'],
                'SWP' => $transfer['SWP']['BEP20'],
                'SWCT' => $transfer['SWCT']['BEP20'],
                'SWP_SS' => $transfer['SWP']['SS'],
            ],
            'exchange' => $data['exchange'],
	        'custom_exchange_commission' => $data['custom_exchange_commission'] ?? $data['exchange'],
	        'custom2_exchange_commission' => $data['custom2_exchange_commission'] ?? $data['exchange'],
            'replenishment' => $data['replenishment'],
        ];
    }

    /**
     * @param array|null $data
     * @return array
     */
    private function _mapToFront(?array $data): array
    {
        if (!isset($data['transfer'])) {
            return [];
        }

        $transfer = $data['transfer'];
        $withdraw = $data['withdraw'];

        return [
            'transfer' => [
                'USD' => ['SW' => $transfer['USD'], 'PM' => $withdraw['USD']],
                'EUR' => ['SW' => $transfer['EUR'], 'PM' => $withdraw['EUR']],
                'BTC' => ['BTC' => $transfer['BTC']],
                'ETH' => ['ERC20' => $transfer['ETH']],
                'USDT' => [
                    'ERC20' => $transfer['ERC20'],
                    'TRC20' =>  $transfer['TRC20'],
                    'BEP20' =>  $transfer['BEP20'],
                ],
                'TRX' => ['TRC20' => $transfer['TRX']],
                'BNB' => ['BEP20' => $transfer['BNB']],

                'SWP' => [
					'BEP20' => $transfer['SWP'],
					'SS' => $withdraw['SWP_SS'],
                ],

                'SWCT' => [
                    'BEP20' => $transfer['SWCT'],
                ],
            ],
            'exchange' => $data['exchange'],
            'custom_exchange_commission' => $data['custom_exchange_commission'] ?? $data['exchange'],
            'custom2_exchange_commission' => $data['custom2_exchange_commission'] ?? $data['exchange'],
            'replenishment' => $data['replenishment'],
        ];
    }

	/**
	 * @param array $data
	 * @return void
	 * @throws InvalidArgumentException
	 */
    private function _changeUsersCommissions(array $data):void
    {
        $oldCommissions = $this->globalSettingsService->get('commissions');
        $changedUsers = $this->userSettingsRepository->getNotNullCommissions();

        /** @var UserSettings $changedUser */
        foreach ($changedUsers as $changedUser) {
            $changedUserCommission = [];

            $userCommissions = $changedUser->getCommissions();
            foreach ($oldCommissions as $type => $values) {
            	if(!isset($userCommissions[$type])){
		            $userCommissions[] = [$type => $values];
	            }

                if (is_array($values)) {
                    foreach ($values as $key => $value) {
                        if ($value !== $userCommissions[$type][$key]) {
                            $changedUserCommission[$type][$key] = $value;
                        }
                    }
                } else {
                    if($values !== $userCommissions[$type]) {
                        $changedUserCommission[$type] = $values;
                    }
                }
            }

            foreach ($data as $newType => $newValues) {
                if (is_array($newValues)) {
                    foreach ($newValues as $newKey => $newValue) {
                        if ($newValue !== $userCommissions[$newType][$newKey] && !isset($changedUserCommission[$newType][$newKey])) {
                            $userCommissions[$newType][$newKey] = $newValue;
                        }
                    }
                } else {
                    if($newValues !== $userCommissions[$newType] && !isset($changedUserCommission[$newType])) {
                        $userCommissions[$newType] = $newValues;
                    }
                }
            }

            $changedUser->setCommissions($userCommissions);

            $this->manager->persist($changedUser);

			$this->commissionsCache->delete("commissions_{$changedUser->getUser()->getId()}");
        }

        $this->manager->flush();

    }
}