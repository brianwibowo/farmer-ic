<?php

namespace App\Http\Controllers;

use App\Http\Requests\Notifications\FilterNotificationsRequest;
use App\Models\Notification;
use App\Models\User;
use App\Policies\NotificationPolicy;
use App\Services\Notifications\NotificationInboxService;
use App\Support\RolePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function unread(Request $request, NotificationInboxService $inbox): JsonResponse
    {
        return app(RolePermissions::class)->withMembershipSnapshot($request->user(), function () use ($request, $inbox): JsonResponse {
            Gate::authorize('viewAny', Notification::class);

            return response()->json(['count' => $inbox->unreadCount($request->user())])->header('Cache-Control', 'no-store');
        });
    }

    public function index(FilterNotificationsRequest $request, NotificationInboxService $inbox): Response
    {
        return app(RolePermissions::class)->withMembershipSnapshot($request->user(), function () use ($request, $inbox): Response {
            Gate::authorize('viewAny', Notification::class);
            $actor = $request->user();
            $filters = $request->validated();
            $context = $this->tenantContext($actor, $filters, $inbox);
            $tenantNames = array_column($context['options'], 'name', 'id');

            return Inertia::render('notifications/Index', [
                'notifications' => $inbox->paginate($actor, ['status' => $filters['status'] ?? 'unread'])->through(fn (Notification $notification): array => [
                    'id' => $notification->id, 'title' => $notification->title,
                    'type' => $notification->type,
                    'content' => $notification->content,
                    'message' => $inbox->messageFor($actor, $notification, isset($tenantNames[$notification->team_id])),
                    'tenant_name' => $tenantNames[$notification->team_id] ?? null,
                    'is_read' => $notification->is_read,
                    'date_key' => $notification->created_at?->setTimezone(config('app.timezone'))->toDateString(),
                    'date_label' => $notification->created_at?->setTimezone(config('app.timezone'))->settings(['locale' => 'id'])->translatedFormat('j F Y'),
                    'created_at' => $notification->created_at?->setTimezone(config('app.timezone'))->settings(['locale' => 'id'])->translatedFormat('j F Y H:i').' WIB',
                    'can_read' => Gate::allows('update', $notification),
                ]),
                'unreadCount' => $inbox->unreadCount($request->user()),
                'hasPhoneNumber' => filled($request->user()->phone_number),
                'fonnteEnabled' => (bool) config('operational_notifications.fonnte.enabled', false),
                'status' => $filters['status'] ?? 'unread',
                'canFilterTenant' => $context['show'],
            ]);
        });
    }

    public function read(FilterNotificationsRequest $request, Notification $notification, NotificationInboxService $inbox): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        Gate::authorize('update', $notification);
        $filters = $request->validated();
        $this->tenantContext($request->user(), $filters, $inbox);
        $inbox->markRead($notification);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Notifikasi ditandai sudah dibaca.']);

        return to_route('notifications.index', $filters);
    }

    /** @param array<string, mixed> $filters
     * @return array{show: bool, options: list<array{id: int, name: string}>}
     */
    private function tenantContext(User $actor, array $filters, NotificationInboxService $inbox): array
    {
        $show = app(NotificationPolicy::class)->showsTenantContext($actor);
        $options = $show ? $inbox->tenantOptions($actor) : [];
        if (! empty($filters['team_id'])) {
            abort_unless($show && in_array((int) $filters['team_id'], array_column($options, 'id'), true), 403);
        }

        return ['show' => $show, 'options' => $options];
    }
}
