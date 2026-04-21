<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupDestination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function index(): Response
    {
        $disks = (array) config('backup.backup.destination.disks', []);
        $name = (string) config('backup.backup.name');

        $summaries = collect($disks)->map(function (string $disk) use ($name): array {
            try {
                $destination = BackupDestination::create($disk, $name);
                $backups = $destination->backups();

                return [
                    'disk' => $disk,
                    'count' => $backups->count(),
                    'used_bytes' => (int) $backups->sum(fn (Backup $b) => $b->sizeInBytes()),
                    'newest_at' => $backups->first()?->date()->toIso8601String(),
                ];
            } catch (\Throwable) {
                return [
                    'disk' => $disk,
                    'count' => 0,
                    'used_bytes' => 0,
                    'newest_at' => null,
                    'error' => true,
                ];
            }
        })->values();

        $backups = collect($disks)
            ->flatMap(function (string $disk) use ($name): array {
                try {
                    $destination = BackupDestination::create($disk, $name);

                    return $destination->backups()->map(fn (Backup $backup) => [
                        'disk' => $disk,
                        'path' => $backup->path(),
                        'size' => (int) $backup->sizeInBytes(),
                        'date' => $backup->date()->toIso8601String(),
                    ])->all();
                } catch (\Throwable) {
                    return [];
                }
            })
            ->sortByDesc('date')
            ->values();

        return Inertia::render('Admin/Backups', [
            'backups' => $backups,
            'disks' => $disks,
            'name' => $name,
            'summaries' => $summaries,
            'config' => [
                'includes' => (array) config('backup.backup.source.files.include', []),
                'excludes' => (array) config('backup.backup.source.files.exclude', []),
                'databases' => (array) config('backup.backup.source.databases', []),
                'retention_daily' => (int) config('backup.cleanup.default_strategy.keep_all_backups_for_days', 7),
                'retention_daily_keep' => (int) config('backup.cleanup.default_strategy.keep_daily_backups_for_days', 16),
                'retention_weekly_keep' => (int) config('backup.cleanup.default_strategy.keep_weekly_backups_for_weeks', 8),
                'retention_monthly_keep' => (int) config('backup.cleanup.default_strategy.keep_monthly_backups_for_months', 4),
                'retention_yearly_keep' => (int) config('backup.cleanup.default_strategy.keep_yearly_backups_for_years', 2),
                'max_storage_mb' => (int) config('backup.cleanup.default_strategy.delete_oldest_backups_when_using_more_megabytes_than', 5000),
            ],
        ]);
    }

    public function run(): RedirectResponse
    {
        Artisan::queue('backup:run', ['--only-db' => false]);

        activity('backup')->event('run')->log('Manual backup queued');

        return back()->with('success', 'Backup queued.');
    }

    public function clean(): RedirectResponse
    {
        Artisan::queue('backup:clean');

        activity('backup')->event('clean')->log('Manual backup cleanup queued');

        return back()->with('success', 'Backup cleanup queued.');
    }

    public function download(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'disk' => ['required', 'string'],
            'path' => ['required', 'string'],
        ]);

        $disks = (array) config('backup.backup.destination.disks', []);
        abort_unless(in_array($data['disk'], $disks, true), 404);

        $storage = Storage::disk($data['disk']);
        abort_unless($storage->exists($data['path']), 404);

        activity('backup')->event('download')->withProperties($data)->log('Backup downloaded');

        $filename = basename($data['path']);

        return $storage->download($data['path'], $filename);
    }

    public function prepareRestore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'disk' => ['required', 'string'],
            'path' => ['required', 'string'],
            'confirm' => ['required', 'string'],
        ]);

        $disks = (array) config('backup.backup.destination.disks', []);
        abort_unless(in_array($data['disk'], $disks, true), 404);
        abort_unless(basename($data['path']) === $data['confirm'], 422, 'Filename confirmation does not match.');

        $storage = Storage::disk($data['disk']);
        abort_unless($storage->exists($data['path']), 404);

        $stagingDir = storage_path('app/backup-restores/'.now()->format('Ymd_His').'_'.Str::random(6));

        if (! is_dir($stagingDir) && ! mkdir($stagingDir, 0755, true) && ! is_dir($stagingDir)) {
            abort(500, 'Unable to create staging directory.');
        }

        $tmpZip = $stagingDir.'/backup.zip';
        file_put_contents($tmpZip, $storage->get($data['path']));

        $zip = new \ZipArchive;
        $opened = $zip->open($tmpZip);
        if ($opened !== true) {
            abort(500, 'Unable to open backup archive (error '.$opened.').');
        }
        $zip->extractTo($stagingDir);
        $zip->close();

        activity('backup')
            ->event('restore_prepared')
            ->withProperties(['path' => $data['path'], 'staging' => $stagingDir])
            ->log('Backup prepared for restore');

        return back()->with('success', 'Backup extracted to '.$stagingDir.'. Finish the restore from the CLI — see the info panel for instructions.');
    }
}
