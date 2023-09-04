<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Helper\LoggerHelper;
use App\Helper\SecurityHelper;
use App\Service\Finance\CommissionService;
use Exception;
use Psr\Cache\InvalidArgumentException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;


/**
 *
 * @Route("/api/admin/commission", name="commission_")
 */
class CommissionController extends AbstractController
{
	/**
	 * @OA\Post(
	 *     path="/api/admin/commission/index",
	 *     summary="Global commissions",
	 *     tags={"Commissions"},
	 *     @OA\RequestBody(
	 *         @OA\MediaType(
	 *             mediaType="application/json"
	 *         )
	 *     ),
	 *     @OA\Response(response="200", description="Returning global commissions data"),
	 *     @OA\Response(response="400", description="Data is not found"),
	 * )
	 *
	 *
	 * @Route("/index", name="commission-index")
	 * @param CommissionService $commissionService
	 * @return JsonResponse
	 * @throws InvalidArgumentException
	 */
	public function index(CommissionService $commissionService): JsonResponse
	{
		try {
			return $this->json(($commissionService->getCommissions()) ?
				$commissionService->getCommissions() : ['Error' => 'Commissions not found!']);
		} catch (Exception $exception) {
			return $this->json(['message' => $exception->getMessage(),], 400);
		}
	}
	
	/**
	 * @OA\Post(
	 *     path="/api/admin/commission/update",
	 *     summary="Update global commissions",
	 *     tags={"Commissions"},
	 *     @OA\RequestBody(
	 *         @OA\MediaType(
	 *             mediaType="application/json",
	 *          @OA\Schema(
	 *                 @OA\Property(
	 *                     property="data",
	 *                     type="array",
	 *                     @OA\Items()
	 *                 ),
	 *                 example={"data":{"withdraw": {"USD": 1, "EUR": 1, "BTC": 0.0005, "ETH": 0.008, "ERC20": 25, "TRC20": 1, "TRX": 1, "SNT": 1 },
	 *                       "transfer": {"USD": 0, "EUR": 0, "BTC": 0.0005, "ETH": 0.008, "ERC20": 10, "TRC20": 1, "TRX": 1, "SNT": 1},
	 *                       "exchange": 0.996, "replenishment":2.3}
	 *                      }
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response="200", description="Returning result of commissions update"),
	 *     @OA\Response(response="400", description="Data is not found"),
	 * )
	 *
	 *
	 * @Route("/update", name="commission-update")
	 * @param Request $request
	 * @param CommissionService $commissionService
	 * @return JsonResponse
	 * @throws InvalidArgumentException
	 */
	public function update(Request $request, CommissionService $commissionService): JsonResponse
	{
		$data = SecurityHelper::encodeArray($request->request->all('data'));
		try {
			$this->validate_exchange('custom_exchange_commission', (float)$data['custom_exchange_commission']);
			$this->validate_exchange('custom2_exchange_commission', (float)$data['custom2_exchange_commission']);
			$this->validate_exchange('exchange', (float)$data['exchange']);
		} catch (Exception $exception) {
			return $this->json(['message' => $exception->getMessage()], 422);
		}
		
		try {
			return $this->json($commissionService->setCommissions($request));
			
		} catch (Exception $exception) {
			return $this->json(['message' => $exception->getMessage() . PHP_EOL . $exception->getFile() . PHP_EOL . $exception->getLine()], 400);
		}
	}
	
	/**
	 * @OA\Post(
	 *     path="/api/admin/commission/user",
	 *     summary="User commissions",
	 *     tags={"Commissions"},
	 *     @OA\RequestBody(
	 *         @OA\MediaType(
	 *             mediaType="application/json",
	 *              @OA\Property (
	 *                  property="user_id",
	 *                  type="integer"
	 *              )
	 *         )
	 *     ),
	 *     @OA\Response(response="200", description="Returning user commissions data"),
	 *     @OA\Response(response="400", description="Data is not found"),
	 * )
	 *
	 * @Route("/user", name="commission-user")
	 * @param CommissionService $commissionService
	 * @param Request $request
	 * @return JsonResponse
	 * @throws InvalidArgumentException
	 */
	public function user(CommissionService $commissionService, Request $request): JsonResponse
	{
		try {
			$result = $commissionService->getUserCommissions((int)$request->request->get('user_id'));
			
			if (isset($result['Error'])) {
				return $this->json($result);
			}
			
			return $this->json($result['data']);
		} catch (Exception $exception) {
			return $this->json(['message' => $exception->getMessage(),], 400);
		}
	}
	
	/**
	 * @OA\Post(
	 *     path="/api/admin/commission/update-user",
	 *     summary="Update user commissions",
	 *     tags={"Commissions"},
	 *     @OA\RequestBody(
	 *         @OA\MediaType(
	 *             mediaType="application/json",
	 *          @OA\Schema(
	 *                 @OA\Property(
	 *                     property="data",
	 *                     type="array",
	 *                     @OA\Items()
	 *                 ),
	 *                 @OA\Property(
	 *                     property="user_id",
	 *                     type="integer"
	 *                 ),
	 *                 example={"data":{"withdraw": {"USD": 1, "EUR": 1, "BTC": 0.0005, "ETH": 0.008, "ERC20": 25, "TRC20": 1, "TRX": 1, "SNT": 1 },
	 *                       "transfer": {"USD": 0, "EUR": 0, "BTC": 0.0005, "ETH": 0.008, "ERC20": 10, "TRC20": 1, "TRX": 1, "SNT": 1},
	 *                       "exchange": 0.996, "replenishment":2.3},
	 *                      "user_id": 1
	 *                      }
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response="200", description="Returning result of commissions update"),
	 *     @OA\Response(response="400", description="Data is not found"),
	 * )
	 *
	 *
	 * @Route("/update-user", name="user-commission-update")
	 * @param Request $request
	 * @param CommissionService $commissionService
	 * @return JsonResponse
	 * @throws InvalidArgumentException
	 */
	public function updateUser(Request $request, CommissionService $commissionService): JsonResponse
	{
		$data = SecurityHelper::encodeArray($request->request->all('data'));
		try {
			$this->validate_exchange('custom_exchange_commission', (float)$data['custom_exchange_commission']);
			$this->validate_exchange('custom2_exchange_commission', (float)$data['custom2_exchange_commission']);
			$this->validate_exchange('exchange', (float)$data['exchange']);
		} catch (Exception $exception) {
			return $this->json(['message' => $exception->getMessage()], 422);
		}
		
		try {
			return $this->json($commissionService->setUserCommissions($request, (int)$request->request->get('user_id')));
		} catch (Exception $exception) {
			return $this->json(['message' => $exception->getMessage(),], 400);
		}
	}
	
	/**
	 * @OA\Post(
	 *     path="/api/admin/commission/exception-users",
	 *     summary="Exception users",
	 *     tags={"Commissions"},
	 *     @OA\RequestBody(
	 *         @OA\MediaType(
	 *             mediaType="application/json"
	 *         )
	 *     ),
	 *     @OA\Response(response="200", description="Returning users with another commissions"),
	 *     @OA\Response(response="400", description="Data is not found"),
	 * )
	 *
	 * @Route("/exception-users", name="exception-users")
	 * @param CommissionService $commissionService
	 * @return JsonResponse
	 */
	public function exceptionUsers(CommissionService $commissionService): JsonResponse
	{
		try {
			return $this->json($commissionService->getExceptionUsers());
		} catch (Exception $exception) {
			return $this->json(['message' => $exception->getMessage(),], 400);
		}
	}
	
	/**
	 * @OA\Post(
	 *     path="/api/admin/commission/reset-user-commissions",
	 *     summary="Reset user commissions",
	 *     tags={"Commissions"},
	 *     @OA\RequestBody(
	 *         @OA\MediaType(
	 *             mediaType="application/json",
	 *              @OA\Property (
	 *                  property="user_id",
	 *                  type="integer"
	 *              )
	 *         )
	 *     ),
	 *     @OA\Response(response="200", description="Returning reset status"),
	 *     @OA\Response(response="400", description="Returning error"),
	 * )
	 *
	 *
	 * @Route("/reset-user-commissions", name="reset-user-commissions")
	 * @param CommissionService $commissionService
	 * @param Request $request
	 * @return JsonResponse
	 * @throws InvalidArgumentException
	 */
	public function resetUserCommissions(CommissionService $commissionService, Request $request): JsonResponse
	{
		try {
			return $this->json($commissionService->resetUserCommissions($request));
		} catch (Exception $exception) {
			return $this->json(['message' => $exception->getMessage(),], 400);
		}
	}
	
	private function validate_exchange(string $name, float $val): void
	{
		if ($val > 1 || $val <= 0)
			throw new Exception('Value ' . $name . ' cant be less than 0.1 or more than 1');
		
	}
	
}
