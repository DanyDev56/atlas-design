<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use Atlas\Composition\Dashboard\DashboardQueryHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardQueryHandler $dashboard,
    ) {}

    public function show(Request $request, string $workspaceId): JsonResponse
    {
        try {
            return response()->json($this->dashboard->getDashboard(
                (string) $request->attributes->get('authenticated_user_id'),
                $workspaceId,
            ));
        } catch (\DomainException $exception) {
            $code = $exception->getMessage() === 'Unauthorized.' ? 403 : 422;

            return response()->json([
                'error' => class_basename($exception),
                'messages' => [$exception->getMessage()],
            ], $code);
        }
    }
}
