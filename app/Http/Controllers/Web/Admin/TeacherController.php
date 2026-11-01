<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\TeacherStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\TeacherRequest;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Services\Accounts\AccountProvisioner;
use App\Services\Teachers\TeacherService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TeacherController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Teacher::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(TeacherStatus::class)],
            'specialty' => ['nullable', 'string', 'max:150'],
            'access' => ['nullable', Rule::in(['none', 'active', 'disabled'])],
            'assignments' => ['nullable', Rule::in(['none', 'assigned'])],
        ]);

        $teachers = Teacher::query()
            ->with('user:id,is_active,must_change_password')
            ->withCount('assignments')
            ->search($filters['search'] ?? null)
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['specialty'] ?? null, fn ($query, string $specialty) => $query->where('specialty', $specialty))
            ->when($filters['access'] ?? null, function ($query, string $access): void {
                match ($access) {
                    'none' => $query->whereNull('user_id'),
                    'active' => $query->whereHas('user', fn ($userQuery) => $userQuery->where('is_active', true)),
                    'disabled' => $query->whereHas('user', fn ($userQuery) => $userQuery->where('is_active', false)),
                };
            })
            ->when($filters['assignments'] ?? null, function ($query, string $assignments): void {
                if ($assignments === 'none') {
                    $query->doesntHave('assignments');

                    return;
                }

                $query->has('assignments');
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        $specialties = Teacher::query()
            ->whereNotNull('specialty')
            ->distinct()
            ->orderBy('specialty')
            ->pluck('specialty', 'specialty')
            ->all();

        return view('admin.teachers.index', compact('teachers', 'specialties'));
    }

    public function create(): View
    {
        Gate::authorize('create', Teacher::class);

        return view('admin.teachers.create');
    }

    public function store(
        TeacherRequest $request,
        TeacherService $service,
        AccountProvisioner $accounts,
    ): RedirectResponse {
        $result = $service->create(
            $request->safe()->except(['photo', 'remove_photo', 'create_account']),
            $request->file('photo'),
            $request->boolean('create_account'),
        );

        $teacher = $result['teacher'];

        $redirect = redirect()
            ->route('admin.teachers.show', $teacher)
            ->with('success', "Enseignant {$teacher->full_name} enregistré. Matricule : {$teacher->matricule}.");

        if ($result['account_created']) {
            if ($accounts->sendPasswordSetupLink($teacher->user()->firstOrFail())) {
                $redirect->with('success', "Enseignant {$teacher->full_name} enregistré. Matricule : {$teacher->matricule}. Un lien de définition du mot de passe a été envoyé par e-mail.");
            } else {
                $redirect->with('error', 'Le compte est créé, mais le lien de définition du mot de passe n’a pas pu être envoyé. Vérifiez la configuration de messagerie puis utilisez « Mot de passe oublié » pour renvoyer le lien.');
            }
        }

        return $redirect;
    }

    public function show(Teacher $teacher): View
    {
        Gate::authorize('view', $teacher);

        $teacher->load(['user', 'documents' => fn ($query) => $query->with('uploader')->latest()]);
        $assignmentGroups = $teacher->assignments()
            ->with(['subject', 'classroom.level', 'classroom.schoolYear'])
            ->get()
            ->groupBy(fn (TeachingAssignment $assignment): int => $assignment->classroom->school_year_id)
            ->map(fn (Collection $assignments): array => [
                'school_year' => $assignments->first()->classroom->schoolYear,
                'assignments' => $assignments,
                'count' => $assignments->count(),
                'weekly_hours' => $assignments->sum('weekly_hours'),
                'unreported_hours' => $assignments->whereNull('weekly_hours')->count(),
            ]);
        $assignmentCount = $assignmentGroups->sum('count');
        $weeklyHoursTotal = $assignmentGroups->sum('weekly_hours');
        $unreportedHoursCount = $assignmentGroups->sum('unreported_hours');

        return view('admin.teachers.show', compact(
            'teacher',
            'assignmentGroups',
            'assignmentCount',
            'weeklyHoursTotal',
            'unreportedHoursCount',
        ));
    }

    public function edit(Teacher $teacher): View
    {
        Gate::authorize('update', $teacher);

        return view('admin.teachers.edit', compact('teacher'));
    }

    public function update(TeacherRequest $request, Teacher $teacher, TeacherService $service): RedirectResponse
    {
        $service->update(
            $teacher,
            $request->safe()->except(['photo', 'remove_photo', 'create_account']),
            $request->file('photo'),
            $request->boolean('remove_photo'),
        );

        return redirect()
            ->route('admin.teachers.show', $teacher)
            ->with('success', 'Fiche enseignant mise à jour.');
    }

    public function destroy(Teacher $teacher, TeacherService $service): RedirectResponse
    {
        Gate::authorize('delete', $teacher);

        try {
            $service->delete($teacher);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.teachers.index')
            ->with('success', "Enseignant {$teacher->full_name} archivé.");
    }
}
