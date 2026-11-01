<?php

use App\Models\CentreSetting;
use App\Models\User;
use App\Services\CentreSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('lets an admin upload a private profile photo for the account only', function () {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('profile.photo.update'), [
            'photo' => UploadedFile::fake()->image('admin.png', 150, 150),
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('status', 'profile-photo-updated');

    $admin->refresh();
    expect($admin->profile_photo_path)->toStartWith("users/{$admin->id}/profile/");
    Storage::disk('local')->assertExists($admin->profile_photo_path);

    $this->actingAs($admin)
        ->get(route('profile.photo'))
        ->assertOk()
        ->assertHeader('cache-control', 'no-store, private')
        ->assertHeader('x-content-type-options', 'nosniff');

    $this->assertDatabaseCount('centre_settings', 0);
    expect(app(CentreSettingsService::class)->current()['logo_path'])->toBeNull()
        ->and(CentreSetting::query()->where('logo_path', $admin->profile_photo_path)->exists())->toBeFalse();
});

it('removes the previous private profile photo when it is replaced', function () {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('profile.photo.update'), [
        'photo' => UploadedFile::fake()->image('first.png', 120, 120),
    ])->assertRedirect(route('profile.edit'));

    $oldPath = $admin->fresh()->profile_photo_path;

    $this->actingAs($admin)->post(route('profile.photo.update'), [
        'photo' => UploadedFile::fake()->image('second.png', 140, 140),
    ])->assertRedirect(route('profile.edit'));

    $newPath = $admin->fresh()->profile_photo_path;
    Storage::disk('local')->assertMissing($oldPath);
    Storage::disk('local')->assertExists($newPath);
    expect($newPath)->not->toBe($oldPath);
});

it('rejects invalid profile photos and denies photo changes to other roles', function () {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();
    $teacher = User::factory()->teacher()->create();

    $this->actingAs($admin)
        ->post(route('profile.photo.update'), [
            'photo' => UploadedFile::fake()->create('payload.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('photo');

    $this->actingAs($teacher)
        ->post(route('profile.photo.update'), [
            'photo' => UploadedFile::fake()->image('photo.png'),
        ])
        ->assertForbidden();

    $this->actingAs($teacher)
        ->get(route('profile.photo'))
        ->assertForbidden();

    $this->assertNull($admin->fresh()->profile_photo_path);
    $this->assertDatabaseCount('centre_settings', 0);
});
