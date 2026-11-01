<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Les photos sont sur un disque privé : on les sert uniquement après vérification
 * de la Policy (admin, parent de CET élève, ou l'élève lui-même).
 */
class StudentPhotoController extends Controller
{
    public function __invoke(Student $student): StreamedResponse
    {
        Gate::authorize('view', $student);

        abort_if(
            $student->photo_path === null || ! Storage::disk('local')->exists($student->photo_path),
            404
        );

        return Storage::disk('local')->response($student->photo_path, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
