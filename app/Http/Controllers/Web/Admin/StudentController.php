<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StudentRequest;
use App\Models\Guardian;
use App\Models\Student;
use App\Services\Students\QrCodeService;
use App\Services\Students\StudentService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Student::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string'],
            'gender' => ['nullable', 'string'],
            'archived' => ['nullable', 'in:active,archived,all'],
        ]);

        $status = StudentStatus::tryFrom($filters['status'] ?? '');
        $gender = Gender::tryFrom($filters['gender'] ?? '');
        $archiveFilter = $filters['archived'] ?? 'active';

        $students = Student::query()
            ->when($archiveFilter === 'archived', fn ($query) => $query->onlyTrashed())
            ->when($archiveFilter === 'all', fn ($query) => $query->withTrashed())
            ->with('guardian')
            ->search($filters['search'] ?? null)
            ->when($status, fn ($q) => $q->where('status', $status->value))
            ->when($gender, fn ($q) => $q->where('gender', $gender->value))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.students.index', compact('students'));
    }

    public function create(): View
    {
        Gate::authorize('create', Student::class);

        return view('admin.students.create', [
            'guardians' => Guardian::orderBy('last_name')->orderBy('first_name')->get(),
        ]);
    }

    public function store(StudentRequest $request, StudentService $service): RedirectResponse
    {
        $student = $service->create(
            $request->safe()->except(['photo', 'remove_photo']),
            $request->file('photo'),
        );

        return redirect()
            ->route('admin.students.show', $student)
            ->with('success', "Élève {$student->full_name} enregistré. Matricule : {$student->matricule}.");
    }

    public function show(Student $student, QrCodeService $qr): View
    {
        Gate::authorize('view', $student);

        $student->load(['guardian', 'enrollments.classroom.level', 'enrollments.schoolYear']);

        return view('admin.students.show', [
            'student' => $student,
            'qrSvg' => $student->trashed() ? null : $qr->svg($student->qr_token, 180),
        ]);
    }

    public function edit(Student $student): View
    {
        Gate::authorize('update', $student);

        return view('admin.students.edit', [
            'student' => $student,
            'guardians' => Guardian::orderBy('last_name')->orderBy('first_name')->get(),
        ]);
    }

    public function update(StudentRequest $request, Student $student, StudentService $service): RedirectResponse
    {
        $service->update(
            $student,
            $request->safe()->except(['photo', 'remove_photo']),
            $request->file('photo'),
            $request->boolean('remove_photo'),
        );

        return redirect()
            ->route('admin.students.show', $student)
            ->with('success', 'Fiche élève mise à jour.');
    }

    public function destroy(Student $student, StudentService $service): RedirectResponse
    {
        Gate::authorize('delete', $student);

        try {
            $service->delete($student);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.students.index')
            ->with('success', "Élève {$student->full_name} archivé.");
    }
}
