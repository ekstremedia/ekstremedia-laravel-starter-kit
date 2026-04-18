<?php

use App\Models\EmailTemplate;
use App\Models\User;
use App\Notifications\AccountBannedNotification;
use App\Notifications\AdminTestNotification;
use App\Notifications\WelcomeNotification;
use App\Services\MjmlCompiler;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(EmailTemplateSeeder::class);
});

it('compiles valid MJML to HTML', function () {
    $compiler = app(MjmlCompiler::class);

    $html = $compiler->compile('<mjml><mj-body><mj-section><mj-column><mj-text>Hello</mj-text></mj-column></mj-section></mj-body></mjml>');

    expect($html)->toContain('Hello');
    expect($html)->toContain('<!doctype html>');
});

it('seeds all 14 templates', function () {
    expect(EmailTemplate::count())->toBe(14);
    expect(EmailTemplate::whereNotNull('compiled_html')->count())->toBe(14);
});

it('finds template by slug and locale', function () {
    $template = EmailTemplate::forSlug('welcome', 'no');

    expect($template)->not->toBeNull();
    expect($template->locale)->toBe('no');
    expect($template->slug)->toBe('welcome');
});

it('falls back to English when locale template is missing', function () {
    // Delete the Norwegian version
    EmailTemplate::query()->where('slug', 'welcome')->where('locale', 'no')->delete();

    $template = EmailTemplate::forSlug('welcome', 'no');

    expect($template)->not->toBeNull();
    expect($template->locale)->toBe('en');
});

it('interpolates variables in rendered HTML', function () {
    $template = EmailTemplate::forSlug('welcome', 'en');

    $html = $template->render(['user_name' => 'Alice', 'app_name' => 'TestApp', 'app_url' => 'https://test.com']);

    expect($html)->toContain('Alice');
});

it('interpolates subject variables', function () {
    $template = EmailTemplate::forSlug('welcome', 'en');

    $subject = $template->interpolateSubject(['app_name' => 'TestApp']);

    expect($subject)->toContain('TestApp');
});

it('sends welcome notification with template', function () {
    Notification::fake();

    $user = User::factory()->create();
    $user->notify(new WelcomeNotification);

    Notification::assertSentTo($user, WelcomeNotification::class);
});

it('sends account banned notification with template', function () {
    Notification::fake();

    $user = User::factory()->create();
    $user->notify(new AccountBannedNotification('Test reason'));

    Notification::assertSentTo($user, AccountBannedNotification::class);
});

it('sends admin test notification with template', function () {
    Notification::fake();

    $user = User::factory()->create();
    $user->notify(new AdminTestNotification('Hello test'));

    Notification::assertSentTo($user, AdminTestNotification::class);
});

it('recompiles when template is updated', function () {
    $template = EmailTemplate::forSlug('welcome', 'en');
    $originalHtml = $template->compiled_html;

    $template->update(['heading' => 'Updated heading!']);
    $template->compile();

    expect($template->compiled_html)->not->toBe($originalHtml);
    expect($template->compiled_html)->toContain('Updated heading!');
});
