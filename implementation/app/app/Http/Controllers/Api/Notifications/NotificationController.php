<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Notifications;

use App\Http\Controllers\Controller;
use Atlas\Modules\Notifications\Application\ChangeNotificationPreferencesHandler;
use Atlas\Modules\Notifications\Application\MarkNotificationReadHandler;
use Atlas\Modules\Notifications\Application\NotificationQueryHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationQueryHandler $queries,
        private readonly MarkNotificationReadHandler $markRead,
        private readonly ChangeNotificationPreferencesHandler $preferences,
    ) {}

    public function index(Request $request, string $workspaceId): JsonResponse
    {
        return $this->respond(fn () => [
            'notifications' => $this->queries->listNotifications(
                $this->actorId($request),
                $workspaceId,
                $request->query('status'),
            ),
        ]);
    }

    public function unreadCount(Request $request, string $workspaceId): JsonResponse
    {
        return $this->respond(fn () => $this->queries->getUnreadCount(
            $this->actorId($request),
            $workspaceId,
        ));
    }

    public function show(Request $request, string $workspaceId, string $notificationId): JsonResponse
    {
        return $this->respond(fn () => $this->queries->getNotification(
            $this->actorId($request),
            $workspaceId,
            $notificationId,
        ));
    }

    public function markRead(Request $request, string $workspaceId, string $notificationId): JsonResponse
    {
        return $this->respond(fn () => $this->markRead->handle(
            $this->actorId($request),
            $workspaceId,
            $notificationId,
            (int) $request->input('expected_revision'),
        ));
    }

    public function getPreferences(Request $request, string $workspaceId): JsonResponse
    {
        return $this->respond(fn () => $this->preferences->getPreferences(
            $this->actorId($request),
            $workspaceId,
        ));
    }

    public function changePreferences(Request $request, string $workspaceId): JsonResponse
    {
        return $this->respond(fn () => $this->preferences->changePreferences(
            actorUserId: $this->actorId($request),
            workspaceId: $workspaceId,
            inAppMode: (string) $request->input('in_app_mode'),
            emailMode: (string) $request->input('email_mode'),
            expectedRevision: (int) $request->input('expected_revision'),
            emailConsentConfirmed: (bool) $request->input('email_consent_confirmed', false),
        ));
    }

    private function actorId(Request $request): string
    {
        return (string) $request->attributes->get('authenticated_user_id');
    }

    /** @param callable(): array<string, mixed> $action */
    private function respond(callable $action, int $status = 200): JsonResponse
    {
        try {
            return response()->json($action(), $status);
        } catch (\DomainException $exception) {
            $code = match ($exception->getMessage()) {
                'Unauthorized.' => 403,
                'Consent required.' => 422,
                default => 422,
            };

            return response()->json([
                'error' => class_basename($exception),
                'messages' => [$exception->getMessage()],
            ], $code);
        }
    }
}
