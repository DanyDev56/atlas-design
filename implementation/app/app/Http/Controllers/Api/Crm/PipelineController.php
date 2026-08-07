<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use Atlas\Modules\Crm\Application\CrmQueryHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PipelineController extends Controller
{
    public function __construct(
        private readonly CrmQueryHandler $queries,
    ) {}

    public function __invoke(Request $request, string $workspaceId): JsonResponse
    {
        try {
            return response()->json($this->queries->getPipeline(
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
