<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FileItem;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        /** @var Tenant|null $customer */
        $customer = $request->attributes->get('customer') ?? tenancy()->tenant;

        $memberCount = $customer?->users()->count() ?? 0;

        $filesStats = null;
        if ($customer?->files_feature_enabled) {
            $filesStats = [
                'count' => FileItem::query()
                    ->where('user_id', $user->getKey())
                    ->where('type', 'file')
                    ->count(),
                'bytes' => (int) FileItem::query()
                    ->where('user_id', $user->getKey())
                    ->where('type', 'file')
                    ->sum('size'),
            ];
        }

        $chatStats = null;
        if (config('chat.enabled', true)) {
            $chatStats = [
                'unread' => $user->unreadMessagesCount(),
            ];
        }

        // Tenant-scoped activity: filter by tenant members. An empty member
        // list would otherwise broadcast every causer's activity across all
        // tenants, so short-circuit to an empty list. Force the central
        // connection because `activity_log` lives in the shared schema, not
        // in tenant DBs (stancl/tenancy switches the default connection
        // when a tenant is initialized).
        $memberIds = $customer?->users()->pluck('users.id')->all() ?? [];
        $activity = [];
        if (! empty($memberIds)) {
            $centralConnection = (string) config('tenancy.database.central_connection');
            $activity = Activity::on($centralConnection)
                ->whereIn('causer_id', $memberIds)
                ->where('causer_type', $user::class)
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (Activity $a) => [
                    'id' => $a->id,
                    'created_at' => $a->created_at?->toIso8601String(),
                    'description' => $a->description,
                    'event' => $a->event,
                    'log_name' => $a->log_name,
                ])
                ->values()
                ->all();
        }

        return Inertia::render('Dashboard', [
            'memberCount' => $memberCount,
            'filesStats' => $filesStats,
            'chatStats' => $chatStats,
            'activity' => $activity,
        ]);
    }
}
