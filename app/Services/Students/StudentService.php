<?php

namespace App\Services\Students;

use App\Models\Student;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StudentService
{
    /** Disque PRIVÉ (storage/app/private) : jamais accessible directement par URL. */
    private const DISK = 'local';

    private const MAX_ATTEMPTS = 5;

    public function __construct(private MatriculeGenerator $matricules) {}

    /**
     * @param  array<string, mixed>  $data  Données validées (sans la photo)
     */
    public function create(array $data, ?UploadedFile $photo = null): Student
    {
        $photoPath = $photo ? $this->storePhoto($photo) : null;
        $attempts = 0;

        while (true) {
            try {
                // Une violation d'unicité abort la transaction PostgreSQL :
                // on réessaie donc à l'extérieur, avec une nouvelle transaction.
                return DB::transaction(function () use ($data, $photoPath) {
                    $student = new Student($data);
                    $student->matricule = $this->matricules->next();
                    $student->qr_token = Str::random(40);
                    $student->photo_path = $photoPath;
                    $student->save();

                    return $student;
                });
            } catch (UniqueConstraintViolationException $e) {
                if (++$attempts >= self::MAX_ATTEMPTS) {
                    $this->deletePhoto($photoPath);

                    throw $e;
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Student $student, array $data, ?UploadedFile $photo = null, bool $removePhoto = false): Student
    {
        $oldPhoto = $student->photo_path;

        $student->fill($data);

        if ($photo) {
            $student->photo_path = $this->storePhoto($photo);
        } elseif ($removePhoto) {
            $student->photo_path = null;
        }

        $student->save();

        if ($oldPhoto !== null && $oldPhoto !== $student->photo_path) {
            $this->deletePhoto($oldPhoto);
        }

        return $student;
    }

    /** Suppression logique : l'historique (notes, paiements...) est conservé. */
    public function delete(Student $student): void
    {
        DB::transaction(function () use ($student) {
            $student = Student::query()
                ->whereKey($student->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($student->enrollments()->active()->exists()) {
                throw new DomainException("Impossible d'archiver cet élève : une inscription est encore en cours. Clôturez-la d'abord.");
            }

            $student->delete();
        });
    }

    private function storePhoto(UploadedFile $photo): string
    {
        // store() génère un nom aléatoire et déduit l'extension du CONTENU réel du fichier,
        // jamais du nom envoyé par le navigateur.
        return $photo->store('students/photos', self::DISK);
    }

    private function deletePhoto(?string $path): void
    {
        if ($path !== null) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
