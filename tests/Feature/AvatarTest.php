<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Storage::fake('public');

    $this->user = User::factory()->create();
    $this->user->assignRole('User');
});

it('requires authentication to upload an avatar', function () {
    $this->post('/profile/avatar', [
        'avatar' => UploadedFile::fake()->image('a.png'),
    ])->assertRedirect('/login');
});

it('uploads an avatar and stores it in the collection', function () {
    $this->actingAs($this->user)
        ->post('/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.png', 400, 400),
        ])
        ->assertRedirect();

    expect($this->user->fresh()->getFirstMedia('avatar'))->not->toBeNull();
});

it('rejects non-image uploads', function () {
    $this->actingAs($this->user)
        ->post('/profile/avatar', [
            'avatar' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('avatar');
});

it('removes the avatar', function () {
    $this->user->addMedia(UploadedFile::fake()->image('a.png', 400, 400))
        ->toMediaCollection('avatar');

    expect($this->user->fresh()->getFirstMedia('avatar'))->not->toBeNull();

    $this->actingAs($this->user)
        ->delete('/profile/avatar')
        ->assertRedirect();

    expect($this->user->fresh()->getFirstMedia('avatar'))->toBeNull();
});
