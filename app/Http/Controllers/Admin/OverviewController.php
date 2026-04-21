<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Spatie\Backup\BackupDestination\BackupDestination;

class OverviewController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Overview', [
            'metrics' => $this->collect(),
        ]);
    }

    public function metrics(): JsonResponse
    {
        return response()->json($this->collect());
    }

    private function collect(): array
    {
        $now = CarbonImmutable::now();
        $thirtyDaysAgo = $now->subDays(30)->startOfDay();
        $sevenDaysAgo = $now->subDays(7)->startOfDay();

        return [
            'generated_at' => $now->toIso8601String(),
            'users' => $this->userMetrics($now, $sevenDaysAgo, $thirtyDaysAgo),
            'customers' => $this->customerMetrics(),
            'storage' => $this->storageMetrics(),
            'queue' => $this->queueMetrics(),
            'backups' => $this->backupMetrics(),
            'activity' => $this->activityMetrics($thirtyDaysAgo, $now),
            'recent_activity' => $this->recentActivity(),
        ];
    }

    private function userMetrics(CarbonImmutable $now, CarbonImmutable $sevenDaysAgo, CarbonImmutable $thirtyDaysAgo): array
    {
        $trend = User::query()
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->selectRaw('date(created_at) as d, count(*) as c')
            ->groupBy('d')
            ->orderBy('d')
            ->pluck('c', 'd');

        return [
            'total' => User::count(),
            'unverified' => User::whereNull('email_verified_at')->count(),
            'banned' => Schema::hasColumn('users', 'banned_at')
                ? User::whereNotNull('banned_at')->count()
                : 0,
            'new_last_7d' => User::where('created_at', '>=', $sevenDaysAgo)->count(),
            'trend_30d' => $this->fillDailySeries($trend, $thirtyDaysAgo, $now),
        ];
    }

    private function customerMetrics(): ?array
    {
        if (! config('tenancy.enabled')) {
            return null;
        }

        return [
            'total' => Tenant::count(),
            'active' => Tenant::where('status', 'active')->count(),
            'suspended' => Tenant::where('status', 'suspended')->count(),
        ];
    }

    private function storageMetrics(): array
    {
        // Quotas live in user_settings JSON (per-user), not on the users table —
        // summing them would require a JSON scan. Surface total usage only.
        return [
            'used_bytes' => (int) User::sum('storage_used_bytes'),
            'quota_bytes' => 0,
        ];
    }

    private function queueMetrics(): array
    {
        return [
            'pending' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0,
            'failed' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
        ];
    }

    private function backupMetrics(): array
    {
        try {
            $disks = (array) config('backup.backup.destination.disks', []);
            if (empty($disks)) {
                return ['last_at' => null, 'last_size_bytes' => null, 'count' => 0];
            }
            $destination = BackupDestination::create($disks[0], (string) config('backup.backup.name'));
            $backups = $destination->backups();
            $newest = $backups->first();

            return [
                'disk' => $disks[0],
                'count' => $backups->count(),
                'last_at' => $newest?->date()->toIso8601String(),
                'last_size_bytes' => $newest ? (int) $newest->sizeInBytes() : null,
            ];
        } catch (\Throwable) {
            return ['last_at' => null, 'last_size_bytes' => null, 'count' => 0];
        }
    }

    private function activityMetrics(CarbonImmutable $thirtyDaysAgo, CarbonImmutable $now): array
    {
        $trend = Activity::query()
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->selectRaw('date(created_at) as d, count(*) as c')
            ->groupBy('d')
            ->orderBy('d')
            ->pluck('c', 'd');

        return [
            'total' => Activity::count(),
            'trend_30d' => $this->fillDailySeries($trend, $thirtyDaysAgo, $now),
        ];
    }

    private function recentActivity(): array
    {
        return Activity::query()
            ->with('causer:id,email,first_name,last_name')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Activity $a) => [
                'id' => $a->id,
                'description' => $a->description,
                'log_name' => $a->log_name,
                'event' => $a->event,
                'created_at' => $a->created_at?->toIso8601String(),
                'causer' => $a->causer ? [
                    'id' => $a->causer->getKey(),
                    'email' => $a->causer->email ?? null,
                    'first_name' => $a->causer->first_name ?? null,
                    'last_name' => $a->causer->last_name ?? null,
                ] : null,
            ])
            ->all();
    }

    /**
     * @param  Collection<string,int>  $data
     * @return array<int, array{date: string, count: int}>
     */
    private function fillDailySeries($data, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $out = [];
        $cursor = $from->startOfDay();
        while ($cursor->lessThanOrEqualTo($to)) {
            $key = $cursor->toDateString();
            $out[] = ['date' => $key, 'count' => (int) ($data[$key] ?? 0)];
            $cursor = $cursor->addDay();
        }

        return $out;
    }
}
