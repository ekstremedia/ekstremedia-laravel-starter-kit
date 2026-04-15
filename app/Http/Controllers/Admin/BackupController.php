<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupDestination;

class BackupController extends Controller
{
    public function index(): Response
    {
        $backups = collect(config('backup.backup.destination.disks', []))
            ->flatMap(function (string $disk): array {
                try {
                    $destination = BackupDestination::create($disk, config('backup.backup.name'));

                    return $destination->backups()->map(fn (Backup $backup) => [
                        'disk' => $disk,
                        'path' => $backup->path(),
                        'size' => $backup->sizeInBytes(),
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
            'disks' => config('backup.backup.destination.disks', []),
            'name' => config('backup.backup.name'),
        ]);
    }

    public function run(): RedirectResponse
    {
        Artisan::queue('backup:run', ['--only-db' => false]);

        return back()->with('success', 'Backup queued.');
    }

    public function clean(): RedirectResponse
    {
        Artisan::queue('backup:clean');

        return back()->with('success', 'Backup cleanup queued.');
    }
}
