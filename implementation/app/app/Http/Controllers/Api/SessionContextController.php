<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Atlas\Modules\Identity\Application\GetSessionContextHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SessionContextController extends Controller
{
    public function __construct(
        private readonly GetSessionContextHandler $handler,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $sessionId = (string) $request->attributes->get('session_id');

        return response()->json($this->handler->handle($userId, $sessionId));
    }
}
