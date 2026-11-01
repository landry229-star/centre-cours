<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TeachingAssignmentRequest;
use App\Models\Classroom;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Services\Academics\TeachingAssignmentService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class TeachingAssignmentController extends Controller
{
    public function store(
        TeachingAssignmentRequest $request,
        Classroom $classroom,
        TeachingAssignmentService $service
    ): RedirectResponse {
        $service->add($classroom, $request->validated());

        return redirect()
            ->route('admin.classrooms.show', $classroom)
            ->with('success', 'Matière affectée.');
    }

    public function edit(TeachingAssignment $assignment): View
    {
        Gate::authorize('update', $assignment);

        $assignment->load(['classroom', 'subject', 'teacher']);

        return view('admin.assignments.edit', [
            'assignment' => $assignment,
            'teachers' => Teacher::where('status', 'active')
                ->orWhere('id', $assignment->teacher_id)
                ->orderBy('last_name')->orderBy('first_name')->get()
                ->mapWithKeys(fn ($t) => [$t->id => $t->full_name])->all(),
        ]);
    }

    public function update(
        TeachingAssignmentRequest $request,
        TeachingAssignment $assignment,
        TeachingAssignmentService $service
    ): RedirectResponse {
        try {
            $service->update($assignment, $request->validated());
        } catch (DomainException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.classrooms.show', $assignment->classroom_id)
            ->with('success', 'Affectation mise à jour.');
    }

    public function destroy(TeachingAssignment $assignment, TeachingAssignmentService $service): RedirectResponse
    {
        Gate::authorize('delete', $assignment);

        $classroomId = $assignment->classroom_id;

        try {
            $service->remove($assignment);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.classrooms.show', $classroomId)
            ->with('success', 'Affectation retirée.');
    }
}
