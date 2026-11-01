<?php

namespace Tests\Feature\Guardians;

use App\Enums\Role;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GuardianManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'adjoa',
            'last_name' => 'agbodjan',
            'phone' => '+229 01 97 00 00 00',
            'email' => 'Adjoa.Agbodjan@Example.com',
            'address' => 'Cotonou',
            'profession' => 'Commerçante',
        ], $overrides);
    }

    public function test_admin_creates_a_guardian_with_a_login_account(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->admin())
            ->post(route('admin.guardians.store'), $this->payload(['create_account' => '1']));

        $guardian = Guardian::firstOrFail();
        $response->assertRedirect(route('admin.guardians.show', $guardian));
        $response->assertSessionMissing('temporary_password');

        $user = $guardian->user;
        $this->assertNotNull($user);
        $this->assertSame(Role::Guardian, $user->role);
        $this->assertSame('adjoa.agbodjan@example.com', $user->email);
        $this->assertTrue($user->must_change_password);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_guardian_without_account_option_gets_no_user(): void
    {
        $admin = $this->admin();
        $existingUserCount = User::count();

        $this->actingAs($admin)->post(route('admin.guardians.store'), $this->payload());

        $this->assertNull(Guardian::firstOrFail()->user_id);
        $this->assertSame($existingUserCount, User::count());
    }

    public function test_account_requires_a_unique_email(): void
    {
        User::factory()->create(['email' => 'adjoa.agbodjan@example.com']);

        $this->actingAs($this->admin())
            ->post(route('admin.guardians.store'), $this->payload(['create_account' => '1']))
            ->assertSessionHasErrors('email');

        $this->actingAs($this->admin())
            ->post(route('admin.guardians.store'), $this->payload(['email' => '', 'create_account' => '1']))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('guardians', 0);
    }

    public function test_account_can_be_created_later(): void
    {
        Notification::fake();

        $guardian = Guardian::factory()->create(['email' => 'parent.tard@example.com']);

        $this->actingAs($this->admin())
            ->post(route('admin.guardians.account.store', $guardian))
            ->assertSessionMissing('temporary_password')
            ->assertSessionHas('success');

        $this->assertNotNull($guardian->fresh()->user_id);
        Notification::assertSentTo($guardian->fresh()->user, ResetPassword::class);
    }

    public function test_guardian_with_students_cannot_be_deleted(): void
    {
        $guardian = Guardian::factory()->create();
        Student::factory()->for($guardian)->create();

        $this->actingAs($this->admin())
            ->delete(route('admin.guardians.destroy', $guardian))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($guardian);
    }

    public function test_deleting_a_guardian_deactivates_the_account(): void
    {
        $user = User::factory()->guardian()->create();
        $guardian = Guardian::factory()->create(['user_id' => $user->id]);

        $this->actingAs($this->admin())->delete(route('admin.guardians.destroy', $guardian));

        $this->assertSoftDeleted($guardian);
        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_non_admin_cannot_access_guardian_pages(): void
    {
        foreach (['teacher', 'guardian', 'student'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create())
                ->get(route('admin.guardians.index'))
                ->assertForbidden();
        }
    }
}
