<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\StorageUsageService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StorageDashboardController extends Controller
{
    public function __construct(private readonly StorageUsageService $usage) {}

    public function index(): Response
    {
        $totalBytes = $this->usage->systemTotalBytes();
        $byType = $this->usage->systemBreakdownByType();
        $byCollection = $this->usage->systemBreakdownByCollection();
        $topUsers = $this->usage->topUsers(10);

        $diskTotal = (int) @disk_total_space(storage_path());
        $diskFree = (int) @disk_free_space(storage_path());

        return Inertia::render('Admin/Storage', [
            'totals' => [
                'bytes' => $totalBytes,
                'disk_total' => $diskTotal,
                'disk_free' => $diskFree,
                'file_count' => (int) DB::connection(config('tenancy.database.central_connection'))
                    ->table('media')->count(),
                'user_count' => User::query()->count(),
            ],
            'by_type' => $byType,
            'by_collection' => $byCollection,
            'top_users' => $topUsers,
            'growth' => $this->growth(),
        ]);
    }

    /**
     * Last 30 days of aggregate usage across all users.
     *
     * @return array<int, array{date: string, bytes: int}>
     */
    private function growth(): array
    {
        $conn = (string) config('tenancy.database.central_connection');

        return DB::connection($conn)
            ->table('storage_snapshots')
            ->whereNull('tenant_id')
            ->where('snapshot_date', '>=', now()->subDays(30)->toDateString())
            ->select('snapshot_date', DB::raw('SUM(bytes_used) as bytes'))
            ->groupBy('snapshot_date')
            ->orderBy('snapshot_date')
            ->get()
            ->map(fn ($r) => [
                'date' => (string) Carbon::parse($r->snapshot_date)->toDateString(),
                'bytes' => (int) $r->bytes,
            ])
            ->all();
    }
}
