<?php

declare(strict_types=1);

namespace Atlas\Platform\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class OperatorAccessEnabledMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('operations.backoffice.enabled', false)) {
            return response()->json([
                'error' => 'BackofficeUnavailable',
                'messages' => ['Le back-office est désactivé.'],
            ], 404);
        }

        $localOrTesting = app()->environment(['local', 'testing']);
        $mfaRequired = (bool) config('operations.backoffice.require_mfa', true);
        $totpExternalAccepted = (bool) config('operations.backoffice.allow_totp_external', false);

        if ($mfaRequired && ($localOrTesting || $totpExternalAccepted)) {
            return $next($request);
        }

        $passwordOnlyAllowed = $localOrTesting
            && config('operations.backoffice.allow_password_only_local', false);

        if (! $mfaRequired && $passwordOnlyAllowed) {
            return $next($request);
        }

        if (! $localOrTesting && $mfaRequired) {
            return response()->json([
                'error' => 'StrongAuthenticationRequired',
                'messages' => ['La MFA TOTP externe exige une acceptation explicite du risque et un accès réseau borné.'],
            ], 503);
        }

        return response()->json([
            'error' => 'StrongAuthenticationRequired',
            'messages' => ['L’authentification opérateur renforcée doit être activée.'],
        ], 503);
    }
}
