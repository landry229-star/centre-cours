<?php

namespace Tests\Feature\Teachers;

use App\Enums\Role;
use App\Models\Classroom;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeacherManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'akouavi',
            'last_name' => 'houngbo',
            'gender' => 'F',
            'phone' => '+229 01 95 00 00 00',
            'email' => 'Akouavi.Houngbo@Example.com',
            'address' => 'Porto-Novo',
            'specialty' => 'Mathématiques',
            'education_level' => 'master',
            'hired_at' => '2024-09-15',
            'status' => 'active',
        ], $overrides);
    }

    public function test_admin_creates_a_teacher_with_generated_matricule(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('admin.teachers.store'), $this->payload([
                'status' => 'inactive',
                'employment_ended_at' => '2025-06-30',
                'emergency_contact_name' => 'Afi Houngbo',
                'emergency_contact_phone' => '+229 01 90 00 00 00',
            ]));

        $teacher = Teacher::firstOrFail();
        $response->assertRedirect(route('admin.teachers.show', $teacher));

        $this->assertSame('HOUNGBO', $teacher->last_name);
        $this->assertSame('Akouavi', $teacher->first_name);
        $this->assertSame(sprintf('ENS-%d-0001', now()->year), $teacher->matricule);
        $this->assertNull($teacher->user_id);
        $this->assertSame('2025-06-30', $teacher->employment_ended_at->toDateString());
        $this->assertSame('Afi Houngbo', $teacher->emergency_contact_name);
        $this->assertSame('+229 01 90 00 00 00', $teacher->emergency_contact_phone);
    }

    public function test_employment_end_date_cannot_precede_recruitment_and_emergency_contact_fields_are_paired(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.teachers.store'), $this->payload([
                'employment_ended_at' => '2024-09-14',
                'emergency_contact_phone' => '+229 01 90 00 00 00',
            ]))
            ->assertSessionHasErrors(['employment_ended_at', 'emergency_contact_name']);

        $this->actingAs($this->admin())
            ->post(route('admin.teachers.store'), $this->payload([
                'employment_ended_at' => '2025-06-30',
                'status' => 'active',
                'emergency_contact_name' => 'Afi Houngbo',
            ]))
            ->assertSessionHasErrors(['status', 'emergency_contact_phone']);

        $this->assertDatabaseCount('teachers', 0);
    }

    public function test_matricule_is_never_reused_after_archiving(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.teachers.store'), $this->payload(['email' => 'a@example.com']));
        $first = Teacher::firstOrFail();
        $this->actingAs($admin)->delete(route('admin.teachers.destroy', $first));
        $this->actingAs($admin)->post(route('admin.teachers.store'), $this->payload(['email' => 'b@example.com']));

        $second = Teacher::latest('id')->firstOrFail();
        $this->assertSame(sprintf('ENS-%d-0002', now()->year), $second->matricule);
    }

    public function test_teacher_account_is_created_with_the_teacher_role(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->admin())
            ->post(route('admin.teachers.store'), $this->payload(['create_account' => '1']));

        $response->assertSessionMissing('temporary_password');

        $teacher = Teacher::firstOrFail();
        $this->assertNotNull($teacher->user);
        $this->assertSame(Role::Teacher, $teacher->user->role);
        $this->assertSame('akouavi.houngbo@example.com', $teacher->user->email);
        $this->assertTrue($teacher->user->must_change_password);
        Notification::assertSentTo($teacher->user, ResetPassword::class);
    }

    public function test_setup_link_is_sent_when_an_account_is_created_later(): void
    {
        Notification::fake();
        $teacher = Teacher::factory()->create(['email' => 'teacher.later@example.com']);

        $this->actingAs($this->admin())
            ->post(route('admin.teachers.account.store', $teacher))
            ->assertSessionMissing('temporary_password')
            ->assertSessionHas('success');

        Notification::assertSentTo($teacher->fresh()->user, ResetPassword::class);
    }

    public function test_setup_link_failure_is_reported_without_exposing_a_password(): void
    {
        $teacher = Teacher::factory()->create(['email' => 'teacher.failure@example.com']);

        Password::shouldReceive('sendResetLink')
            ->once()
            ->with(['email' => $teacher->email])
            ->andReturn(Password::RESET_THROTTLED);

        $this->actingAs($this->admin())
            ->post(route('admin.teachers.account.store', $teacher))
            ->assertSessionHas('error')
            ->assertSessionMissing('temporary_password');

        $this->assertNotNull($teacher->fresh()->user_id);
    }

    public function test_account_requires_a_unique_email(): void
    {
        User::factory()->create(['email' => 'akouavi.houngbo@example.com']);

        $this->actingAs($this->admin())
            ->post(route('admin.teachers.store'), $this->payload(['create_account' => '1']))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('teachers', 0);
    }

    public function test_teacher_email_must_be_unique_case_insensitively(): void
    {
        Teacher::factory()->create(['email' => 'Teacher.Duplicate@Example.com']);

        $this->actingAs($this->admin())
            ->post(route('admin.teachers.store'), $this->payload(['email' => 'teacher.duplicate@example.com']))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('teachers', 1);
    }

    public function test_active_teacher_list_filters_by_specialty_status_access_and_assignments(): void
    {
        $withoutAccount = Teacher::factory()->create([
            'first_name' => 'Sans',
            'last_name' => 'ACCES',
            'specialty' => 'Mathématiques',
            'status' => 'active',
        ]);
        $withAccount = Teacher::factory()->create([
            'first_name' => 'Avec',
            'last_name' => 'ACCES',
            'specialty' => 'Mathématiques',
            'status' => 'active',
        ]);
        $otherSpecialty = Teacher::factory()->create([
            'first_name' => 'Autre',
            'last_name' => 'MATIERE',
            'specialty' => 'Français',
            'status' => 'inactive',
        ]);
        $user = User::factory()->teacher()->create();
        $withAccount->forceFill(['user_id' => $user->id])->save();
        TeachingAssignment::factory()->create(['teacher_id' => $withAccount->id]);

        $this->actingAs($this->admin())
            ->get(route('admin.teachers.index', [
                'specialty' => 'Mathématiques',
                'status' => 'active',
                'access' => 'none',
                'assignments' => 'none',
            ]))
            ->assertOk()
            ->assertSee($withoutAccount->full_name)
            ->assertSee('Sans accès')
            ->assertSee('Aucune')
            ->assertDontSee($withAccount->full_name)
            ->assertDontSee($otherSpecialty->full_name);

        $this->actingAs($this->admin())
            ->get(route('admin.teachers.index', [
                'specialty' => 'Mathématiques',
                'status' => 'active',
                'access' => 'active',
                'assignments' => 'assigned',
            ]))
            ->assertSee($withAccount->full_name)
            ->assertDontSee($withoutAccount->full_name);
    }

    public function test_non_admin_roles_cannot_manage_teachers(): void
    {
        foreach (['teacher', 'guardian', 'student'] as $role) {
            $user = User::factory()->{$role}()->create();

            $this->actingAs($user)->get(route('admin.teachers.index'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.teachers.store'), $this->payload())->assertForbidden();
        }

        $this->assertDatabaseCount('teachers', 0);
    }

    public function test_validation_rejects_invalid_data(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.teachers.store'), [])
            ->assertSessionHasErrors(['first_name', 'last_name', 'gender', 'phone', 'specialty', 'education_level', 'status']);

        $this->actingAs($this->admin())
            ->post(route('admin.teachers.store'), $this->payload(['hired_at' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors('hired_at');
    }

    public function test_search_filters_the_list(): void
    {
        Teacher::factory()->create(['last_name' => 'ADJOVI', 'first_name' => 'Brice']);
        Teacher::factory()->create(['last_name' => 'KPADONOU', 'first_name' => 'Carine']);

        $this->actingAs($this->admin())
            ->get(route('admin.teachers.index', ['search' => 'adjovi']))
            ->assertOk()
            ->assertSee('ADJOVI')
            ->assertDontSee('KPADONOU');
    }

    public function test_teacher_profile_groups_assignments_by_year_and_links_to_their_filtered_schedule(): void
    {
        $teacher = Teacher::factory()->create();
        $firstYear = SchoolYear::factory()->create(['label' => '2025-2026']);
        $secondYear = SchoolYear::factory()->create(['label' => '2026-2027']);
        $firstClassroom = Classroom::factory()->create(['school_year_id' => $firstYear->id]);
        $secondClassroom = Classroom::factory()->create(['school_year_id' => $secondYear->id]);
        $subject = Subject::factory()->create();
        TeachingAssignment::factory()->create([
            'classroom_id' => $firstClassroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'weekly_hours' => 5,
        ]);
        TeachingAssignment::factory()->create([
            'classroom_id' => $secondClassroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'weekly_hours' => 3,
        ]);
        TeachingAssignment::factory()->create([
            'classroom_id' => $secondClassroom->id,
            'subject_id' => Subject::factory()->create()->id,
            'teacher_id' => $teacher->id,
            'weekly_hours' => null,
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.teachers.show', $teacher))
            ->assertOk()
            ->assertSee('2025-2026')
            ->assertSee('2026-2027')
            ->assertSee('8 h/semaine déclarées')
            ->assertSee('1 affectation(s) sans volume horaire renseigné.')
            ->assertSee(route('admin.schedules.index', [
                'school_year_id' => $firstYear->id,
                'teacher_id' => $teacher->id,
            ]))
            ->assertSee(route('admin.schedules.index', [
                'school_year_id' => $secondYear->id,
                'teacher_id' => $teacher->id,
            ]));
    }

    public function test_teacher_with_assignments_cannot_be_archived(): void
    {
        $teacher = Teacher::factory()->create();
        TeachingAssignment::factory()->create(['teacher_id' => $teacher->id]);

        $this->actingAs($this->admin())
            ->delete(route('admin.teachers.destroy', $teacher))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($teacher);
    }

    public function test_head_teacher_cannot_be_archived(): void
    {
        $teacher = Teacher::factory()->create();
        Classroom::factory()->create(['head_teacher_id' => $teacher->id]);

        $this->actingAs($this->admin())
            ->delete(route('admin.teachers.destroy', $teacher))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($teacher);
    }

    public function test_archiving_a_teacher_deactivates_the_account(): void
    {
        $user = User::factory()->teacher()->create();
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);

        $this->actingAs($this->admin())->delete(route('admin.teachers.destroy', $teacher));

        $this->assertSoftDeleted($teacher);
        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_ending_employment_deactivates_the_teacher_account(): void
    {
        $admin = $this->admin();
        $user = User::factory()->teacher()->create();
        $teacher = Teacher::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.teachers.update', $teacher), $this->payload([
                'email' => $user->email,
                'status' => 'inactive',
                'employment_ended_at' => now()->toDateString(),
            ]))
            ->assertRedirect(route('admin.teachers.show', $teacher));

        $this->assertFalse($user->fresh()->is_active);
        $this->assertSame('inactive', $teacher->fresh()->status->value);
    }

    public function test_inactive_teacher_cannot_create_an_account_and_an_active_teacher_account_can_be_reactivated(): void
    {
        $inactiveTeacher = Teacher::factory()->inactive()->create(['email' => 'inactive.teacher@example.com']);

        $this->actingAs($this->admin())
            ->post(route('admin.teachers.account.store', $inactiveTeacher))
            ->assertSessionHas('error');

        $this->assertNull($inactiveTeacher->fresh()->user_id);

        $user = User::factory()->inactive()->teacher()->create();
        $activeTeacher = Teacher::factory()->create(['user_id' => $user->id]);

        $this->actingAs($this->admin())
            ->patch(route('admin.teachers.account.update', $activeTeacher))
            ->assertRedirect(route('admin.teachers.show', $activeTeacher))
            ->assertSessionHas('success');

        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_only_admins_can_create_teacher_accounts(): void
    {
        $teacher = Teacher::factory()->create(['email' => 'restricted.teacher@example.com']);
        $unauthorizedUser = User::factory()->teacher()->create();
        $userCountBeforeRequest = User::count();

        $this->actingAs($unauthorizedUser)
            ->post(route('admin.teachers.account.store', $teacher))
            ->assertForbidden();

        $this->assertNull($teacher->fresh()->user_id);
        $this->assertSame($userCountBeforeRequest, User::count());
    }

    public function test_teacher_account_setup_link_can_be_resent_without_revealing_a_password(): void
    {
        Notification::fake();
        $user = User::factory()->teacher()->create();
        $user->forceFill(['must_change_password' => true])->save();
        $teacher = Teacher::factory()->create(['user_id' => $user->id, 'email' => $user->email]);

        $this->actingAs($this->admin())
            ->post(route('admin.teachers.account.resend-setup-link', $teacher))
            ->assertSessionHas('success')
            ->assertSessionMissing('temporary_password');

        Notification::assertSentTo($user, ResetPassword::class);
        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_photo_is_private_and_protected_by_the_policy(): void
    {
        Storage::fake('local');
        $admin = $this->admin();

        $this->actingAs($admin)->post(
            route('admin.teachers.store'),
            $this->payload(['photo' => UploadedFile::fake()->image('photo.jpg', 300, 300)])
        );

        $teacher = Teacher::firstOrFail();
        $this->assertStringStartsWith('teachers/photos/', $teacher->photo_path);
        Storage::disk('local')->assertExists($teacher->photo_path);

        $owner = User::factory()->teacher()->create();
        $teacher->forceFill(['user_id' => $owner->id])->save();

        $this->actingAs($admin)->get(route('teachers.photo', $teacher))->assertOk();
        $this->actingAs($owner)->get(route('teachers.photo', $teacher))->assertOk();
        $this->actingAs(User::factory()->teacher()->create())->get(route('teachers.photo', $teacher))->assertForbidden();
        $this->actingAs(User::factory()->guardian()->create())->get(route('teachers.photo', $teacher))->assertForbidden();
    }

    public function test_photo_must_be_a_real_image(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin())->post(
            route('admin.teachers.store'),
            $this->payload(['photo' => UploadedFile::fake()->create('script.pdf', 100, 'application/pdf')])
        )->assertSessionHasErrors('photo');

        $this->assertDatabaseCount('teachers', 0);
    }

    public function test_admin_can_upload_and_download_a_private_teacher_document(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $teacher = Teacher::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.teachers.documents.store', $teacher), [
                'type' => 'contract',
                'document_date' => '2025-09-01',
                'expires_at' => '2026-08-31',
                'file' => UploadedFile::fake()->create('contrat.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect(route('admin.teachers.show', $teacher))
            ->assertSessionHasNoErrors();

        $document = $teacher->documents()->firstOrFail();
        Storage::disk('local')->assertExists($document->path);
        $this->assertStringStartsWith("teachers/documents/{$teacher->id}/", $document->path);
        $this->assertDatabaseHas('teacher_documents', [
            'id' => $document->id,
            'type' => 'contract',
            'document_date' => '2025-09-01',
            'expires_at' => '2026-08-31',
            'uploaded_by' => $admin->id,
            'original_filename' => 'contrat.pdf',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.teachers.show', $teacher))
            ->assertOk()
            ->assertSee('Documents administratifs')
            ->assertSee('contrat.pdf');

        $this->actingAs($admin)
            ->get(route('admin.teachers.documents.show', [$teacher, $document]))
            ->assertDownload('contrat.pdf')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_teacher_documents_are_only_available_to_admins_and_scoped_to_their_teacher(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $teacher = Teacher::factory()->create();

        $this->actingAs($admin)->post(route('admin.teachers.documents.store', $teacher), [
            'type' => 'diploma',
            'file' => UploadedFile::fake()->create('diplome.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $document = $teacher->documents()->firstOrFail();
        $otherTeacher = Teacher::factory()->create();

        foreach (['teacher', 'guardian', 'student'] as $role) {
            $user = User::factory()->{$role}()->create();

            $this->actingAs($user)
                ->post(route('admin.teachers.documents.store', $teacher), [
                    'type' => 'diploma',
                    'file' => UploadedFile::fake()->create('interdit.pdf', 100, 'application/pdf'),
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->get(route('admin.teachers.documents.show', [$teacher, $document]))
                ->assertForbidden();
        }

        $this->actingAs($admin)
            ->get(route('admin.teachers.documents.show', [$otherTeacher, $document]))
            ->assertNotFound();
    }

    public function test_teacher_document_upload_rejects_unsupported_files_and_expiry_before_document_date(): void
    {
        Storage::fake('local');
        $teacher = Teacher::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.teachers.documents.store', $teacher), [
                'type' => 'identity',
                'document_date' => '2025-09-01',
                'expires_at' => '2025-08-31',
                'file' => UploadedFile::fake()->create('payload.php', 100, 'application/x-php'),
            ])
            ->assertSessionHasErrors(['expires_at', 'file']);

        $this->assertDatabaseCount('teacher_documents', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
