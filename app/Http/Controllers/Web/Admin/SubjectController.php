<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubjectRequest;
use App\Models\Subject;
use App\Services\Academics\SubjectService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class SubjectController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Subject::class);

        return view('admin.subjects.index', [
            'subjects' => Subject::query()->withCount('assignments')->orderBy('name')->get(),
        ]);
    }

    public function store(SubjectRequest $request, SubjectService $service): RedirectResponse
    {
        $subject = $service->create($request->validated());

        return redirect()
            ->route('admin.subjects.index')
            ->with('success', "Matière {$subject->name} ajoutée.");
    }

    public function edit(Subject $subject): View
    {
        Gate::authorize('update', $subject);

        return view('admin.subjects.edit', compact('subject'));
    }

    public function update(SubjectRequest $request, Subject $subject, SubjectService $service): RedirectResponse
    {
        $service->update($subject, $request->validated());

        return redirect()
            ->route('admin.subjects.index')
            ->with('success', 'Matière mise à jour.');
    }

    public function destroy(Subject $subject, SubjectService $service): RedirectResponse
    {
        Gate::authorize('delete', $subject);

        try {
            $service->delete($subject);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.subjects.index')
            ->with('success', "Matière {$subject->name} supprimée.");
    }
}
