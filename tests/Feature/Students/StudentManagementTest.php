<?php

namespace Tests\Feature\Students;

use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use App\Services\Students\StudentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function payload(Guardian $guardian, array $overrides = []): array
    {
        return array_merge([
            'guardian_id' => $guardian->id,
            'first_name' => 'koffi',
            'last_name' => 'dossou',
            'gender' => 'M',
            'birth_date' => '2012-05-14',
            'birth_place' => 'Cotonou',
            'phone' => '',
            'address' => 'Abomey-Calavi',
            'guardian_relationship' => 'father',
            'emergency_contact_name' => 'Marie Dossou',
            'emergency_contact_phone' => '+229 01 96 00 00 00',
            'status' => 'active',
        ], $overrides);
    }

    public function test_admin_can_create_a_student(): void
    {
        $guardian = Guardian::factory()->create();

        $response = $this->actingAs($this->admin())
            ->post(route('admin.students.store'), $this->payload($guardian));

        $student = Student::firstOrFail();
        $response->assertRedirect(route('admin.students.show', $student));

        $this->assertSame('DOSSOU', $student->last_name);
        $this->assertSame('Koffi', $student->first_name);
        $this->assertSame(sprintf('ELV-%d-0001', now()->year), $student->matricule);
        $this->assertSame(40, strlen($student->qr_token));
        $this->assertSame($guardian->id, $student->guardian_id);
    }

    public function test_matricules_are_sequential_and_never_reused(): void
    {
        $guardian = Guardian::factory()->create();
        $service = app(StudentService::class);
        $data = $this->payload($guardian);

        $first = $service->create($data);
        $second = $service->create($data);
        $service->delete($second);
        $third = $service->create($data);

        $year = now()->year;
        $this->assertSame("ELV-{$year}-0001", $first->matricule);
        $this->assertSame("ELV-{$year}-0002", $second->matricule);
        $this->assertSame("ELV-{$year}-0003", $third->matricule);
    }

    public function test_non_admin_roles_cannot_manage_students(): void
    {
        $guardian = Guardian::factory()->create();

        foreach (['teacher', 'guardian', 'student'] as $role) {
            $user = User::factory()->{$role}()->create();

            $this->actingAs($user)->get(route('admin.students.index'))->assertForbidden();
            $this->actingAs($user)
                ->post(route('admin.students.store'), $this->payload($guardian))
                ->assertForbidden();
        }

        $this->assertDatabaseCount('students', 0);
    }

    public function test_validation_rejects_invalid_data(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.students.store'), [])
            ->assertSessionHasErrors([
                'guardian_id', 'first_name', 'last_name', 'gender',
                'birth_date', 'guardian_relationship', 'status',
            ]);

        $guardian = Guardian::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.students.store'), $this->payload($guardian, ['birth_date' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors('birth_date');

        $this->assertDatabaseCount('students', 0);
    }

    public function test_search_filters_the_list(): void
    {
        $guardian = Guardian::factory()->create(['first_name' => 'Paul', 'last_name' => 'GBENOU']);
        Student::factory()->for($guardian)->create(['first_name' => 'Alice', 'last_name' => 'ZOUNON']);
        Student::factory()->for($guardian)->create(['first_name' => 'Bertrand', 'last_name' => 'HOUNKPATIN']);

        $this->actingAs($this->admin())
            ->get(route('admin.students.index', ['search' => 'alice']))
            ->assertOk()
            ->assertSee('ZOUNON')
            ->assertDontSee('HOUNKPATIN');
    }

    public function test_photo_is_stored_on_the_private_disk_and_type_is_checked(): void
    {
        Storage::fake('local');
        $guardian = Guardian::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post(
            route('admin.students.store'),
            $this->payload($guardian, ['photo' => UploadedFile::fake()->image('photo.jpg', 300, 300)])
        );

        $student = Student::firstOrFail();
        $this->assertStringStartsWith('students/photos/', $student->photo_path);
        Storage::disk('local')->assertExists($student->photo_path);

        $this->actingAs($admin)->post(
            route('admin.students.store'),
            $this->payload($guardian, [
                'photo' => UploadedFile::fake()->create('script.pdf', 100, 'application/pdf'),
            ])
        )->assertSessionHasErrors('photo');

        $this->assertDatabaseCount('students', 1);
    }

    public function test_deleting_a_student_is_a_soft_delete(): void
    {
        $student = Student::factory()->create(['last_name' => 'ARCHIVE']);

        $this->actingAs($this->admin())
            ->delete(route('admin.students.destroy', $student))
            ->assertRedirect(route('admin.students.index'));

        $this->assertSoftDeleted($student);

        // On vérifie la liste elle-même : le message flash « Élève ARCHIVE … archivé »
        // est encore en session au prochain GET et contient le nom.
        $students = $this->actingAs($this->admin())
            ->get(route('admin.students.index'))
            ->assertOk()
            ->viewData('students');

        $this->assertFalse($students->contains('id', $student->id));
    }

    public function test_student_with_an_active_enrollment_cannot_be_archived(): void
    {
        $student = Student::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'classroom_id' => Classroom::factory()->create()->id,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.students.destroy', $student))
            ->assertSessionHas('error', "Impossible d'archiver cet élève : une inscription est encore en cours. Clôturez-la d'abord.");

        $this->assertNotSoftDeleted($student);
    }

    public function test_student_with_only_closed_enrollments_can_be_archived(): void
    {
        $student = Student::factory()->create();
        Enrollment::factory()->withdrawn()->create([
            'student_id' => $student->id,
            'classroom_id' => Classroom::factory()->create()->id,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.students.destroy', $student))
            ->assertRedirect(route('admin.students.index'));

        $this->assertSoftDeleted($student);
    }

    public function test_archived_student_history_remains_available_to_administrators(): void
    {
        $student = Student::factory()->create();
        $classroom = Classroom::factory()->create();
        $enrollment = Enrollment::factory()->withdrawn()->create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'school_year_id' => $classroom->school_year_id,
        ]);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete(route('admin.students.destroy', $student))
            ->assertRedirect(route('admin.students.index'));

        $this->actingAs($admin)
            ->get(route('admin.students.index', ['archived' => 'archived']))
            ->assertOk()
            ->assertSee($student->full_name)
            ->assertSee('Archivé');

        $this->actingAs($admin)
            ->get(route('admin.students.show', $student))
            ->assertOk()
            ->assertSee('consultable en lecture seule')
            ->assertSee($classroom->name)
            ->assertDontSee('QR Code de présence');

        $this->actingAs($admin)
            ->get(route('admin.students.edit', $student))
            ->assertNotFound();

        $this->assertModelExists($enrollment);
    }
}
