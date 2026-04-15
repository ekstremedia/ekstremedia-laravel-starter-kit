<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TestMail;
use App\Models\MailSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class MailSettingsController extends Controller
{
    public function show(): Response
    {
        $settings = MailSetting::current();

        return Inertia::render('Admin/Mail', [
            'settings' => [
                'mailer' => $settings->mailer,
                'host' => $settings->host,
                'port' => $settings->port,
                'encryption' => $settings->encryption,
                'username' => $settings->username,
                'password' => $settings->password ? '••••••' : null,
                'from_address' => $settings->from_address,
                'from_name' => $settings->from_name,
                'enabled' => $settings->enabled,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mailer' => ['required', 'string', 'max:50'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['nullable', 'string', 'max:20'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'enabled' => ['boolean'],
        ]);

        $settings = MailSetting::current();

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $settings->fill($data)->save();

        activity('mail_settings')
            ->performedOn($settings)
            ->withProperties(['password_changed' => isset($data['password'])])
            ->event('updated')
            ->log('Updated mail settings');

        return back()->with('success', 'Mail settings saved.');
    }

    public function test(Request $request): RedirectResponse
    {
        MailSetting::current()->applyToConfig();

        try {
            Mail::to($request->user()->email)->queue(new TestMail('Sent from Admin → Mail Settings'));
        } catch (Throwable $e) {
            return back()->with('error', 'Mail test failed: '.$e->getMessage());
        }

        return back()->with('success', 'Test mail queued to '.$request->user()->email);
    }
}
