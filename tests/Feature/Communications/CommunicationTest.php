<?php

namespace Tests\Feature\Communications;

use App\Models\Announcement;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\GuardianConversation;
use App\Models\GuardianMessage;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\User;
use App\Services\Communications\CommunicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_for_communications(): void
    {
        $this->get(route('communications.announcements.index'))->assertRedirect(route('login'));
        $this->get(route('communications.messages.index'))->assertRedirect(route('login'));
    }

    public function test_admin_publishes_a_general_announcement_once_per_active_family(): void
    {
        $schoolYear = SchoolYear::factory()->active()->create();
        $classroom = Classroom::factory()->create(['school_year_id' => $schoolYear->id]);
        $family = $this->family($classroom, $schoolYear, ['siblings' => 2]);
        $otherFamily = $this->family($classroom, $schoolYear);
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.communications.announcements.store'), [
            'title' => 'Réunion de rentrée',
            'body' => 'La réunion aura lieu vendredi.',
        ]);

        $announcement = Announcement::firstOrFail();
        $response->assertRedirect(route('communications.announcements.show', $announcement));
        $this->assertDatabaseCount('announcement_user', 2);
        $this->assertDatabaseHas('announcement_user', [
            'announcement_id' => $announcement->id,
            'user_id' => $family['user']->id,
        ]);
        $this->assertDatabaseHas('announcement_user', [
            'announcement_id' => $announcement->id,
            'user_id' => $otherFamily['user']->id,
        ]);
        expect($family['user']->notifications()->count())->toBe(1);
        expect($otherFamily['user']->notifications()->firstOrFail()->data['category'])->toBe('announcement');
    }

    public function test_announcement_preferences_do_not_hide_announcements_or_disable_private_message_notifications(): void
    {
        $schoolYear = SchoolYear::factory()->active()->create();
        $classroom = Classroom::factory()->create(['school_year_id' => $schoolYear->id]);
        $family = $this->family($classroom, $schoolYear);
        $family['user']->forceFill([
            'notification_preferences' => ['announcement' => false],
        ])->save();
        $admin = User::factory()->admin()->create();
        $communications = app(CommunicationService::class);

        $announcement = $communications->publishAnnouncement([
            'title' => 'Annonce silencieuse',
            'body' => 'Toujours consultable dans les communications.',
        ], $admin);

        expect($family['user']->notifications()->count())->toBe(0);
        expect($announcement->recipients()->whereKey($family['user']->id)->exists())->toBeTrue();

        $communications->sendMessage($admin, $family['guardian'], 'Message à ne pas manquer.');

        expect($family['user']->notifications()->count())->toBe(1);
        expect($family['user']->notifications()->firstOrFail()->data['category'])->toBe('message');
    }

    public function test_class_announcement_reaches_only_families_with_active_enrollments_in_that_class(): void
    {
        $schoolYear = SchoolYear::factory()->active()->create();
        $targetClassroom = Classroom::factory()->create(['school_year_id' => $schoolYear->id]);
        $otherClassroom = Classroom::factory()->create(['school_year_id' => $schoolYear->id]);
        $targetFamily = $this->family($targetClassroom, $schoolYear);
        $otherFamily = $this->family($otherClassroom, $schoolYear);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.communications.announcements.store'), [
            'title' => 'Sortie scolaire',
            'body' => 'Pensez à apporter le formulaire signé.',
            'classroom_id' => $targetClassroom->id,
        ])->assertRedirect();

        $announcement = Announcement::firstOrFail();
        expect($announcement->audience)->toBe('class');
        expect($announcement->recipients()->pluck('users.id')->all())->toBe([$targetFamily['user']->id]);
        expect($targetFamily['user']->notifications()->count())->toBe(1);
        expect($otherFamily['user']->notifications()->count())->toBe(0);
    }

    public function test_announcement_is_not_published_when_no_family_matches_its_audience(): void
    {
        $schoolYear = SchoolYear::factory()->active()->create();
        $classroom = Classroom::factory()->create(['school_year_id' => $schoolYear->id]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.communications.announcements.store'), [
                'title' => 'Information de classe',
                'body' => 'Aucun destinataire pour le moment.',
                'classroom_id' => $classroom->id,
            ])
            ->assertSessionHasErrors('audience');

        $this->assertDatabaseCount('announcements', 0);
        $this->assertDatabaseCount('announcement_user', 0);
    }

    public function test_guardian_sees_only_announcements_sent_to_their_account_and_viewing_marks_them_read(): void
    {
        $schoolYear = SchoolYear::factory()->active()->create();
        $classroom = Classroom::factory()->create(['school_year_id' => $schoolYear->id]);
        $ownerFamily = $this->family($classroom, $schoolYear);
        $otherFamily = $this->family($classroom, $schoolYear);
        $admin = User::factory()->admin()->create();
        $announcement = app(CommunicationService::class)
            ->publishAnnouncement(['title' => 'Information', 'body' => 'Texte réservé aux familles'], $admin);
        $announcement->recipients()->updateExistingPivot($otherFamily['user']->id, ['read_at' => now()]);

        $this->actingAs($ownerFamily['user'])
            ->get(route('communications.announcements.index'))
            ->assertSee('Information')
            ->assertSee('Nouvelle');

        $this->actingAs($ownerFamily['user'])
            ->get(route('communications.announcements.show', $announcement))
            ->assertOk()
            ->assertSeeText('Texte réservé aux familles');

        expect($announcement->recipients()->whereKey($ownerFamily['user']->id)->firstOrFail()->pivot->read_at)->not->toBeNull();
    }

    public function test_guardian_cannot_open_an_announcement_for_another_family(): void
    {
        $schoolYear = SchoolYear::factory()->active()->create();
        $classroom = Classroom::factory()->create(['school_year_id' => $schoolYear->id]);
        $owner = $this->family($classroom, $schoolYear);
        $other = $this->family($classroom, $schoolYear);
        $announcement = app(CommunicationService::class)
            ->publishAnnouncement(['title' => 'Privée', 'body' => 'Contenu'], User::factory()->admin()->create());
        $announcement->recipients()->detach($other['user']);

        $this->actingAs($other['user'])
            ->get(route('communications.announcements.show', $announcement))
            ->assertNotFound();
    }

    public function test_announcement_title_and_body_are_escaped_in_the_family_inbox(): void
    {
        $schoolYear = SchoolYear::factory()->active()->create();
        $classroom = Classroom::factory()->create(['school_year_id' => $schoolYear->id]);
        $family = $this->family($classroom, $schoolYear);
        $announcement = app(CommunicationService::class)
            ->publishAnnouncement([
                'title' => '<script>alert(1)</script>',
                'body' => '<img src=x onerror=alert(1)>',
            ], User::factory()->admin()->create());

        $this->actingAs($family['user'])
            ->get(route('communications.announcements.show', $announcement))
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    public function test_guardian_and_admin_exchange_private_messages_with_read_receipts(): void
    {
        $schoolYear = SchoolYear::factory()->active()->create();
        $classroom = Classroom::factory()->create(['school_year_id' => $schoolYear->id]);
        $family = $this->family($classroom, $schoolYear);
        $admin = User::factory()->admin()->create();

        $this->actingAs($family['user'])
            ->post(route('communications.messages.store'), ['body' => 'Bonjour, une question sur les horaires.'])
            ->assertRedirect();

        $conversation = GuardianConversation::firstOrFail();
        $message = GuardianMessage::firstOrFail();
        expect($message->sender_id)->toBe($family['user']->id);
        expect($admin->notifications()->count())->toBe(1);

        $this->actingAs($admin)
            ->get(route('communications.messages.index'))
            ->assertSeeText($family['guardian']->full_name)
            ->assertSeeText('Bonjour, une question sur les horaires.');

        $this->actingAs($admin)
            ->get(route('communications.messages.show', $conversation))
            ->assertSeeText('Bonjour, une question sur les horaires.');
        expect($message->fresh()->read_at)->not->toBeNull();

        $this->actingAs($admin)
            ->post(route('communications.messages.reply', $conversation), ['body' => 'Bonjour, les cours débutent à 8 h.'])
            ->assertRedirect(route('communications.messages.show', $conversation));
        expect($family['user']->notifications()->count())->toBe(1);

        $reply = GuardianMessage::query()->where('sender_id', $admin->id)->firstOrFail();
        $this->actingAs($family['user'])
            ->get(route('communications.messages.show', $conversation))
            ->assertSeeText('Bonjour, les cours débutent à 8 h.');
        expect($reply->fresh()->read_at)->not->toBeNull();
    }

    public function test_conversations_are_private_and_message_content_is_escaped(): void
    {
        $schoolYear = SchoolYear::factory()->active()->create();
        $classroom = Classroom::factory()->create(['school_year_id' => $schoolYear->id]);
        $owner = $this->family($classroom, $schoolYear);
        $stranger = $this->family($classroom, $schoolYear);

        $this->actingAs($owner['user'])
            ->post(route('communications.messages.store'), ['body' => '<script>alert(1)</script>'])
            ->assertRedirect();

        $conversation = GuardianConversation::firstOrFail();
        $this->actingAs($stranger['user'])
            ->get(route('communications.messages.show', $conversation))
            ->assertNotFound();
        $this->actingAs($stranger['user'])
            ->post(route('communications.messages.reply', $conversation), ['body' => 'Intrusion'])
            ->assertNotFound();

        $this->actingAs($owner['user'])
            ->get(route('communications.messages.show', $conversation))
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_admin_can_start_a_private_conversation_with_a_family(): void
    {
        $schoolYear = SchoolYear::factory()->active()->create();
        $classroom = Classroom::factory()->create(['school_year_id' => $schoolYear->id]);
        $family = $this->family($classroom, $schoolYear);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('communications.messages.store'), [
                'guardian_id' => $family['guardian']->id,
                'body' => 'Nous souhaitons vous rencontrer.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('guardian_messages', [
            'sender_id' => $admin->id,
            'body' => 'Nous souhaitons vous rencontrer.',
        ]);
        expect($family['user']->notifications()->count())->toBe(1);
    }

    public function test_teachers_cannot_use_family_communications_and_invalid_messages_are_rejected(): void
    {
        foreach ([
            User::factory()->teacher()->create(),
            User::factory()->student()->create(),
        ] as $user) {
            $this->actingAs($user)
                ->get(route('communications.announcements.index'))
                ->assertForbidden();

            $this->actingAs($user)
                ->get(route('communications.messages.index'))
                ->assertForbidden();
        }

        $schoolYear = SchoolYear::factory()->active()->create();
        $classroom = Classroom::factory()->create(['school_year_id' => $schoolYear->id]);
        $family = $this->family($classroom, $schoolYear);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.communications.announcements.store'), ['title' => '', 'body' => ''])
            ->assertSessionHasErrors(['title', 'body']);
        $this->actingAs($family['user'])
            ->post(route('communications.messages.store'), [
                'guardian_id' => $this->family($classroom, $schoolYear)['guardian']->id,
                'body' => 'Ne doit pas être redirigé.',
            ])
            ->assertSessionHasErrors('guardian_id');
    }

    /** @param array{siblings?: int} $options
     * @return array{user: User, guardian: Guardian, students: array<int, Student>, enrollments: array<int, Enrollment>}
     */
    private function family(Classroom $classroom, SchoolYear $schoolYear, array $options = []): array
    {
        $user = User::factory()->guardian()->create();
        $guardian = Guardian::factory()->create(['user_id' => $user->id]);
        $students = [];
        $enrollments = [];

        for ($index = 0; $index < ($options['siblings'] ?? 1); $index++) {
            $student = Student::factory()->for($guardian)->create();
            $students[] = $student;
            $enrollments[] = Enrollment::factory()->create([
                'student_id' => $student->id,
                'classroom_id' => $classroom->id,
                'school_year_id' => $schoolYear->id,
                'enrolled_at' => $schoolYear->start_date->toDateString(),
            ]);
        }

        return compact('user', 'guardian', 'students', 'enrollments');
    }
}
