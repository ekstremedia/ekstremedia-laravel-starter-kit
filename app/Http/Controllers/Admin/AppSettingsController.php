<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class AppSettingsController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Admin/AppSettings', [
            'settings' => AppSetting::current()->only([
                'site_up', 'registration_open', 'login_enabled', 'require_email_verification',
                'default_role', 'require_2fa_for_admins', 'send_welcome_notification',
                'maintenance_message', 'announcement_banner', 'announcement_severity',
            ]),
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_up' => ['required', 'boolean'],
            'registration_open' => ['required', 'boolean'],
            'login_enabled' => ['required', 'boolean'],
            'require_email_verification' => ['required', 'boolean'],
            'default_role' => ['required', 'string', 'exists:roles,name'],
            'require_2fa_for_admins' => ['required', 'boolean'],
            'send_welcome_notification' => ['required', 'boolean'],
            'maintenance_message' => ['nullable', 'string', 'max:500'],
            'announcement_banner' => ['nullable', 'string', 'max:500'],
            'announcement_severity' => ['required', 'in:info,warn,danger,success'],
        ]);

        $settings = AppSetting::current();
        $changes = collect($data)
            ->filter(fn ($v, $k) => $v != $settings->$k)
            ->keys()
            ->values()
            ->all();

        $settings->fill($data)->save();

        activity('app_settings')
            ->performedOn($settings)
            ->withProperties(['changed' => $changes])
            ->event('updated')
            ->log('Updated app settings');

        return back()->with('success', 'Settings saved.');
    }
}
