<?php

namespace App\Services\Teachers;

use App\Enums\Role;
use App\Enums\TeacherStatus;
use App\Models\Classroom;
use App\Models\Teacher;
use App\Services\Accounts\AccountProvisioner;
use App\Services\Media\PrivatePhotoStorage;
use App\Services\Students\MatriculeGenerator;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class TeacherService
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private MatriculeGenerator $matricules,
        private AccountProvisioner $accounts,
        private PrivatePhotoStorage $photos,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{teacher: Teacher, account_created: bool}
     */
    public function create(array $data, ?UploadedFile $photo = null, bool $withAccount = false): array
    {
        $photoPath = $photo ? $this->photos->store($photo, 'teachers/photos') : null;
        $attempts = 0;

        while (true) {
            try {
                // Retry hors transaction : une violation d'unicité annule la transaction PostgreSQL.
                return DB::transaction(function () use ($data, $photoPath, $withAccount) {
                    $teacher = new Teacher($data);
                    $teacher->matricule = $this->matricules->nextTeacher();
                    $teacher->photo_path = $photoPath;
                    $teacher->save();

                    if ($withAccount) {
                        $this->createAccount($teacher);
                    }

                    return ['teacher' => $teacher, 'account_created' => $withAccount];
                });
            } catch (UniqueConstraintViolationException $e) {
                if (++$attempts >= self::MAX_ATTEMPTS) {
                    $this->photos->delete($photoPath);

                    throw $e;
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Teacher $teacher, array $data, ?UploadedFile $photo = null, bool $removePhoto = false): Teacher
    {
        return DB::transaction(function () use ($teacher, $data, $photo, $removePhoto) {
            $oldPhoto = $teacher->photo_path;

            $teacher->fill($data);

            if ($photo) {
                $teacher->photo_path = $this->photos->store($photo, 'teachers/photos');
            } elseif ($removePhoto) {
                $teacher->photo_path = null;
            }

            $teacher->save();

            if ($teacher->user !== null) {
                $teacher->user->update([
                    'name' => trim("{$teacher->first_name} {$teacher->last_name}"),
                    'email' => $teacher->email,
                ]);
            }

            if (
                $teacher->status === TeacherStatus::Inactive
                || $teacher->employment_ended_at !== null
            ) {
                $teacher->user()->update(['is_active' => false]);
            }

            if ($oldPhoto !== null && $oldPhoto !== $teacher->photo_path) {
                $this->photos->delete($oldPhoto);
            }

            return $teacher;
        });
    }

    public function createAccount(Teacher $teacher): void
    {
        if ($teacher->user_id !== null) {
            throw new DomainException('Cet enseignant possède déjà un accès.');
        }

        if ($teacher->status !== TeacherStatus::Active || $teacher->employment_ended_at !== null) {
            throw new DomainException('Un accès ne peut être créé que pour un enseignant en activité.');
        }

        if (blank($teacher->email)) {
            throw new DomainException('Une adresse email est nécessaire pour créer un accès.');
        }

        $user = $this->accounts->provision(
            trim("{$teacher->first_name} {$teacher->last_name}"),
            $teacher->email,
            Role::Teacher,
        );

        $teacher->user()->associate($user)->save();
    }

    public function reactivateAccount(Teacher $teacher): void
    {
        if ($teacher->user_id === null) {
            throw new DomainException('Cet enseignant ne possède aucun compte à réactiver.');
        }

        if ($teacher->status !== TeacherStatus::Active || $teacher->employment_ended_at !== null) {
            throw new DomainException('Seul un enseignant actif sans date de fin d’emploi peut retrouver un accès.');
        }

        $teacher->user()->update(['is_active' => true]);
    }

    public function delete(Teacher $teacher): void
    {
        if ($teacher->assignments()->exists() || Classroom::where('head_teacher_id', $teacher->id)->exists()) {
            throw new DomainException(
                "Impossible d'archiver cet enseignant : il a des affectations ou une classe principale. Réaffectez-les d'abord."
            );
        }

        DB::transaction(function () use ($teacher) {
            if ($teacher->user !== null) {
                $teacher->user->forceFill(['is_active' => false])->save();
            }

            $teacher->delete();
        });
    }
}
