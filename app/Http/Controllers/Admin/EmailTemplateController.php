<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TemplateMail;
use App\Models\EmailTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class EmailTemplateController extends Controller
{
    public function update(Request $request, EmailTemplate $template): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'heading' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'action_text' => ['nullable', 'string', 'max:255'],
            'action_url' => ['nullable', 'string', 'max:500'],
        ]);

        $template->update($data);
        $template->compile();

        return back()->with('success', "Template \"{$template->name}\" updated.");
    }

    public function preview(EmailTemplate $template): JsonResponse
    {
        if (! $template->compiled_html) {
            $template->compile();
        }

        $sampleData = $this->sampleData($template);

        return response()->json([
            'html' => $template->render($sampleData),
        ]);
    }

    public function testSend(Request $request, EmailTemplate $template): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        if (! $template->compiled_html) {
            $template->compile();
        }

        $sampleData = $this->sampleData($template);
        $subject = $template->interpolateSubject($sampleData);
        $html = $template->render($sampleData);

        Mail::to($data['email'])->send(new TemplateMail($subject, $html));

        return back()->with('success', "Test email sent to {$data['email']}.");
    }

    /**
     * @return array<string, string>
     */
    private function sampleData(EmailTemplate $template): array
    {
        $samples = [
            'user_name' => 'John',
            'user_full_name' => 'John Doe',
            'user_email' => 'john@example.com',
            'customer_name' => 'Acme Corp',
            'reason' => 'Violation of terms of service.',
            'message' => 'Hello from the admin panel!',
            'app_name' => config('app.name'),
            'app_url' => config('app.url'),
            'verification_url' => config('app.url').'/verify/sample',
            'reset_url' => config('app.url').'/reset-password/sample-token',
            'expire_minutes' => '60',
        ];

        $data = [];
        /** @var list<string> $variables */
        $variables = $template->variables ?? [];
        foreach ($variables as $var) {
            $data[$var] = $samples[$var] ?? "[$var]";
        }

        return $data;
    }
}
