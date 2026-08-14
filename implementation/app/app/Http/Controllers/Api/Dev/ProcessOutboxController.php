<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Dev;

use App\Http\Controllers\Controller;
use Atlas\Platform\Messaging\Infrastructure\OutboxProcessor;
use Illuminate\Http\JsonResponse;

final class ProcessOutboxController extends Controller
{
    public function __invoke(OutboxProcessor $processor): JsonResponse
    {
        $count = $processor->processPending();

        return response()->json(['processed' => $count]);
    }
}
