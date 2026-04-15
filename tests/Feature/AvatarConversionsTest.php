<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Storage::fake('public');
});

it('generates the thumb conversion synchronously on upload', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $this->actingAs($user)
        ->post('/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('a.png', 400, 400),
        ])
        ->assertRedirect();

    $media = $user->fresh()->getFirstMedia('avatar');

    expect($media)->not->toBeNull()
        ->and($media->hasGeneratedConversion('thumb'))->toBeTrue();
});

it('updates the avatar on re-upload', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $this->actingAs($user)
        ->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('first.png', 400, 400)])
        ->assertRedirect();

    $firstId = $user->fresh()->getFirstMedia('avatar')->id;

    $this->actingAs($user)
        ->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('second.png', 400, 400)])
        ->assertRedirect();

    $latest = $user->fresh()->getMedia('avatar')->last();

    expect($latest->id)->not->toBe($firstId)
        ->and($latest->file_name)->toBe('second.png');
});

it('enforces max 5 MB upload size', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $this->actingAs($user)
        ->post('/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('big.png')->size(6000),
        ])
        ->assertSessionHasErrors('avatar');
});
