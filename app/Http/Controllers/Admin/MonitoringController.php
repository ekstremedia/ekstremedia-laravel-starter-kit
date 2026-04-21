<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class MonitoringController extends Controller
{
    private const VALID_TABS = ['activity', 'logs', 'pulse', 'horizon'];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'log_name' => ['nullable', 'string', 'max:100'],
            'event' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $tab = $request->string('tab')->toString();
        if (! in_array($tab, self::VALID_TABS, true)) {
            $tab = 'activity';
        }

        $activities = null;
        $users = collect();
        $logNames = collect();
        $events = collect();

        if ($tab === 'activity') {
            $activities = Activity::query()
                ->with('causer:id,first_name,last_name,email')
                ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->where('causer_id', $v)->where('causer_type', User::class))
                ->when($filters['log_name'] ?? null, fn ($q, $v) => $q->where('log_name', $v))
                ->when($filters['event'] ?? null, fn ($q, $v) => $q->where('event', $v))
                ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
                ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
                ->latest()
                ->paginate(25)
                ->withQueryString();

            $users = User::orderBy('email')->get(['id', 'email', 'first_name', 'last_name']);
            $logNames = Activity::query()->select('log_name')->distinct()->whereNotNull('log_name')->pluck('log_name');
            $events = Activity::query()->select('event')->distinct()->whereNotNull('event')->pluck('event');
        }

        return Inertia::render('Admin/Monitoring', [
            'tab' => $tab,
            'activities' => $activities,
            'filters' => $filters,
            'users' => $users,
            'logNames' => $logNames,
            'events' => $events,
            'endpoints' => $this->iframeEndpoints(),
        ]);
    }

    /**
     * Resolve iframe URLs from package config so custom paths keep working.
     *
     * @return array{logs: string, pulse: string, horizon: string}
     */
    private function iframeEndpoints(): array
    {
        return [
            'logs' => '/'.ltrim((string) config('log-viewer.route_path', 'log-viewer'), '/'),
            'pulse' => '/'.ltrim((string) config('pulse.path', 'pulse'), '/'),
            'horizon' => '/'.ltrim((string) config('horizon.path', 'horizon'), '/'),
        ];
    }
}
