<?php

namespace App\Services;

use App\Models\CentreSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CentreSettingsService
{
    /** @return array{name: string, address: ?string, phone: ?string, email: ?string, logo_path: ?string} */
    public function current(): array
    {
        $settings = CentreSetting::query()->find(1);

        if ($settings === null) {
            return [
                'name' => config('centre.name'),
                'address' => config('centre.address'),
                'phone' => config('centre.phone'),
                'email' => config('centre.email'),
                'logo_path' => null,
            ];
        }

        return [
            'name' => $settings->name,
            'address' => $settings->address,
            'phone' => $settings->phone,
            'email' => $settings->email,
            'logo_path' => $settings->logo_path,
        ];
    }

    /**
     * @param  array{name: string, address: ?string, phone: ?string, email: ?string}  $data
     */
    public function update(array $data, ?UploadedFile $logo): CentreSetting
    {
        $settings = CentreSetting::query()->find(1);
        $oldLogoPath = $settings?->logo_path;
        $newLogoPath = null;

        if ($logo !== null) {
            $newLogoPath = $logo->store('centre/logo', 'local');

            if ($newLogoPath === false) {
                throw new RuntimeException('Le logo du centre n’a pas pu être enregistré.');
            }
        }

        $settings ??= new CentreSetting;
        $settings->id = 1;
        $settings->fill($data);

        if ($newLogoPath !== null) {
            $settings->logo_path = $newLogoPath;
        }

        $settings->save();

        if ($newLogoPath !== null && $oldLogoPath !== null && $oldLogoPath !== $newLogoPath) {
            if (! Storage::disk('local')->delete($oldLogoPath)) {
                Log::warning('Ancien logo du centre non supprimé après remplacement.', ['path' => $oldLogoPath]);
            }
        }

        return $settings;
    }

    public function logoDataUri(): ?string
    {
        $storedPath = CentreSetting::query()->find(1)?->logo_path;

        if ($storedPath !== null) {
            if (! Storage::disk('local')->exists($storedPath)) {
                Log::warning('Le fichier du logo du centre est introuvable.', ['path' => $storedPath]);

                return null;
            }

            $mime = Storage::disk('local')->mimeType($storedPath);

            if (! in_array($mime, ['image/png', 'image/jpeg'], true)) {
                Log::warning('Le type du fichier logo du centre ne peut pas être intégré aux documents.', [
                    'path' => $storedPath,
                    'mime' => $mime,
                ]);

                return null;
            }

            return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('local')->get($storedPath));
        }

        $legacyLogo = config('centre.logo');

        if (! is_string($legacyLogo) || $legacyLogo === '') {
            return null;
        }

        $path = public_path(ltrim($legacyLogo, '/'));

        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $mime = mime_content_type($path);

        if (! in_array($mime, ['image/png', 'image/jpeg'], true)) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path));
    }
}
