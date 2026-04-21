<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        // $request->validate() only returns keys that were present. Backfill
        // nulls so the Vue page's v-model bindings (filters.date_from, ...)
        // never hit `undefined` on first render.
        $filters = array_merge(
            ['user_id' => null, 'log_name' => null, 'event' => null, 'date_from' => null, 'date_to' => null],
            $filters,
        );

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
            // Distinct() on activity_log would full-scan the table every time
            // the page renders. The set of log_names / events barely changes,
            // so a short cache is a big hit on larger installations.
            $logNames = Cache::remember('monitoring.activity.log_names', 300, fn () => Activity::query()->select('log_name')->distinct()->whereNotNull('log_name')->pluck('log_name')->values()->all());
            $events = Cache::remember('monitoring.activity.events', 300, fn () => Activity::query()->select('event')->distinct()->whereNotNull('event')->pluck('event')->values()->all());
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
