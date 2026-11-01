<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Stockage des photos sur le disque PRIVÉ (storage/app/private). */
class PrivatePhotoStorage
{
    private const DISK = 'local';

    public function store(UploadedFile $photo, string $directory): string
    {
        $path = $photo->store($directory, self::DISK);

        if ($path === false) {
            throw new RuntimeException('La photo n’a pas pu être enregistrée.');
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path !== null && ! Storage::disk(self::DISK)->delete($path)) {
            throw new RuntimeException('Le fichier privé n’a pas pu être supprimé.');
        }
    }
}
