<?php

declare(strict_types=1);

namespace Atlas\Platform\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireRecentOperatorStepUpMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $stepUpAt = $request->attributes->get('operator_step_up_at');
        if (! is_string($stepUpAt) || $stepUpAt === '') {
            return $this->required();
        }

        try {
            $verifiedAt = new \DateTimeImmutable($stepUpAt);
        } catch (\Throwable) {
            return $this->required();
        }

        $minutes = max(1, min(30, (int) config('operations.backoffice.step_up_minutes', 10)));
        $expiresAt = $verifiedAt->modify('+'.$minutes.' minutes');
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        if ($expiresAt <= $now) {
            return $this->required();
        }

        return $next($request);
    }

    private function required(): Response
    {
        return response()->json([
            'error' => 'OperatorStepUpRequired',
            'messages' => ['Une authentification renforcée récente est requise.'],
        ], 403);
    }
}
