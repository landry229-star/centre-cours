<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TeacherDocumentRequest;
use App\Models\Teacher;
use App\Models\TeacherDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TeacherDocumentController extends Controller
{
    public function store(TeacherDocumentRequest $request, Teacher $teacher): RedirectResponse
    {
        Gate::authorize('update', $teacher);

        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            throw new RuntimeException('Aucun fichier valide n’a été fourni pour le document.');
        }

        $path = $file->store("teachers/documents/{$teacher->id}", 'local');

        if ($path === false) {
            throw new RuntimeException('Le document n’a pas pu être stocké de manière sécurisée.');
        }

        $originalFilename = basename(str_replace('\\', '/', $file->getClientOriginalName()));

        try {
            $teacher->documents()->create([
                ...$request->safe()->except('file'),
                'uploaded_by' => $request->user()->id,
                'original_filename' => Str::limit($originalFilename, 255, ''),
                'path' => $path,
            ]);
        } catch (Throwable $exception) {
            if (! Storage::disk('local')->delete($path)) {
                report(new RuntimeException('Le fichier privé n’a pas pu être supprimé après l’échec de son enregistrement.', previous: $exception));
            }

            throw $exception;
        }

        return redirect()
            ->route('admin.teachers.show', $teacher)
            ->with('success', 'Document enseignant ajouté et stocké en privé.');
    }

    public function show(Teacher $teacher, TeacherDocument $document): StreamedResponse
    {
        Gate::authorize('update', $teacher);

        abort_if(
            ! Storage::disk('local')->exists($document->path),
            404,
        );

        return Storage::disk('local')->download($document->path, $document->original_filename, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
