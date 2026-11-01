<?php

use App\Models\CentreSetting;
use App\Models\User;
use App\Services\CentreSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('shows centre settings to an administrator', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.settings.centre.edit'))
        ->assertOk()
        ->assertSee('Paramètres du centre')
        ->assertSee(config('centre.name'))
        ->assertSee('Logo sur les documents');
});

it('saves centre contact information and a logo for generated documents', function () {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.settings.centre.update'), [
            'name' => 'Centre Scolaire Exemple',
            'address' => 'Abomey-Calavi',
            'phone' => '+229 21 00 00 00',
            'email' => 'contact@example.test',
            'logo' => UploadedFile::fake()->image('logo.png', 120, 80),
        ])
        ->assertRedirect(route('admin.settings.centre.edit'))
        ->assertSessionHas('success', 'Les informations du centre ont été mises à jour.');

    $settings = CentreSetting::query()->findOrFail(1);

    expect($settings->name)->toBe('Centre Scolaire Exemple')
        ->and($settings->address)->toBe('Abomey-Calavi')
        ->and($settings->phone)->toBe('+229 21 00 00 00')
        ->and($settings->email)->toBe('contact@example.test');

    Storage::disk('local')->assertExists($settings->logo_path);
    expect(app(CentreSettingsService::class)->logoDataUri())->toStartWith('data:image/png;base64,');

    $this->actingAs($admin)
        ->get(route('admin.settings.centre.logo'))
        ->assertOk()
        ->assertHeader('cache-control', 'no-store, private')
        ->assertHeader('x-content-type-options', 'nosniff');

    $this->actingAs($admin)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('Centre Scolaire Exemple');
});

it('rejects invalid centre contact data and logo files', function () {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.settings.centre.update'), [
            'name' => 'Centre Exemple',
            'email' => 'not-an-email',
            'logo' => UploadedFile::fake()->create('payload.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors(['email', 'logo']);

    $this->assertDatabaseCount('centre_settings', 0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('denies centre settings access to non-administrators', function () {
    $teacher = User::factory()->teacher()->create();

    $this->actingAs($teacher)
        ->get(route('admin.settings.centre.edit'))
        ->assertForbidden();

    $this->actingAs($teacher)
        ->put(route('admin.settings.centre.update'), ['name' => 'Intrusion'])
        ->assertForbidden();

    $this->assertDatabaseCount('centre_settings', 0);
});
