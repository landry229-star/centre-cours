<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeacherPhotoController extends Controller
{
    public function __invoke(Teacher $teacher): StreamedResponse
    {
        Gate::authorize('view', $teacher);

        abort_if(
            $teacher->photo_path === null || ! Storage::disk('local')->exists($teacher->photo_path),
            404
        );

        return Storage::disk('local')->response($teacher->photo_path, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
