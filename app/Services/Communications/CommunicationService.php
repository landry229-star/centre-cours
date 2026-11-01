<?php

namespace App\Services\Communications;

use App\Enums\ClassroomStatus;
use App\Enums\NotificationCategory;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\Announcement;
use App\Models\Classroom;
use App\Models\Guardian;
use App\Models\GuardianConversation;
use App\Models\GuardianMessage;
use App\Models\SchoolYear;
use App\Models\User;
use App\Notifications\SchoolActivityNotifier;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CommunicationService
{
    public function __construct(private SchoolActivityNotifier $notifier) {}

    /** @param array{title: string, body: string, classroom_id?: int|string|null} $data */
    public function publishAnnouncement(array $data, User $author): Announcement
    {
        [$announcement, $recipientIds] = DB::transaction(function () use ($data, $author): array {
            $classroom = null;
            $schoolYear = null;

            if (! empty($data['classroom_id'])) {
                $schoolYear = SchoolYear::current();
                $classroom = Classroom::query()
                    ->whereKey($data['classroom_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($schoolYear === null
                    || $classroom->school_year_id !== $schoolYear->id
                    || $classroom->status !== ClassroomStatus::Active) {
                    throw new DomainException('Les annonces ciblées doivent concerner une classe active de l’année scolaire en cours.');
                }
            }

            $recipients = User::query()
                ->where('role', Role::Guardian)
                ->where('is_active', true)
                ->whereHas('guardian.students', function ($students) use ($classroom, $schoolYear): void {
                    $students->where('status', StudentStatus::Active);

                    if ($classroom !== null && $schoolYear !== null) {
                        $students->whereHas('enrollments', fn ($enrollments) => $enrollments
                            ->active()
                            ->where('classroom_id', $classroom->id)
                            ->where('school_year_id', $schoolYear->id));
                    }
                })
                ->orderBy('id')
                ->pluck('id');

            if ($recipients->isEmpty()) {
                throw new DomainException('Aucun compte parent actif ne correspond à cette audience.');
            }

            $announcement = new Announcement;
            $announcement->forceFill([
                'created_by' => $author->id,
                'classroom_id' => $classroom?->id,
                'school_year_id' => $schoolYear?->id,
                'title' => $data['title'],
                'body' => $data['body'],
                'audience' => $classroom === null ? 'all' : 'class',
                'published_at' => now(),
            ])->save();
            $announcement->recipients()->attach($recipients->all());

            return [$announcement, $recipients];
        });

        $recipientIds->chunk(100)->each(function (Collection $recipientIds) use ($announcement): void {
            User::query()
                ->whereIn('id', $recipientIds)
                ->orderBy('id')
                ->get()
                ->each(fn (User $recipient) => $this->notifier->send(
                    $recipient,
                    NotificationCategory::Announcement,
                    'Nouvelle annonce du centre',
                    $announcement->title,
                    route('communications.announcements.show', $announcement),
                ));
        });

        return $announcement;
    }

    public function sendMessage(User $sender, Guardian $guardian, string $body): GuardianMessage
    {
        [$message, $recipientIds] = DB::transaction(function () use ($sender, $guardian, $body): array {
            $guardian = Guardian::query()->whereKey($guardian->id)->lockForUpdate()->firstOrFail();
            $guardianUser = $guardian->user;

            if ($guardianUser === null || ! $guardianUser->is_active) {
                throw new DomainException('Ce parent ne possède pas de compte actif.');
            }

            if ($sender->isGuardian() && (int) $guardianUser->id !== (int) $sender->id) {
                throw new DomainException('Vous ne pouvez écrire que dans votre propre conversation.');
            }

            if (! $sender->isAdmin() && ! $sender->isGuardian()) {
                throw new DomainException('Ce compte ne peut pas envoyer de message à l’administration.');
            }

            $conversation = GuardianConversation::query()
                ->where('guardian_id', $guardian->id)
                ->first();

            if ($conversation === null) {
                $conversation = new GuardianConversation;
                $conversation->forceFill(['guardian_id' => $guardian->id])->save();
            }
            $conversation = GuardianConversation::query()
                ->whereKey($conversation->id)
                ->lockForUpdate()
                ->firstOrFail();
            $message = new GuardianMessage;
            $message->forceFill([
                'guardian_conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'body' => $body,
            ])->save();
            $conversation->forceFill(['last_message_at' => $message->created_at])->save();

            $recipientIds = $sender->isGuardian()
                ? User::query()->where('role', Role::Admin)->where('is_active', true)->orderBy('id')->pluck('id')
                : collect([$guardianUser->id]);

            return [$message, $recipientIds];
        });

        $conversation = $message->conversation;
        $recipientIds->each(function (int|string $recipientId) use ($sender, $conversation): void {
            $recipient = User::query()->findOrFail($recipientId);
            $this->notifier->send(
                $recipient,
                NotificationCategory::Message,
                'Nouveau message',
                $sender->name.' vous a envoyé un message.',
                route('communications.messages.show', $conversation),
            );
        });

        return $message;
    }

    public function markMessagesRead(GuardianConversation $conversation, User $reader): void
    {
        $conversation->messages()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $reader->id)
            ->update(['read_at' => now()]);
    }
}
