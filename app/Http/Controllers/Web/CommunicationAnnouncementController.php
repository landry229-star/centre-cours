<?php

namespace App\Http\Controllers\Web;

use App\Enums\ClassroomStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnnouncementRequest;
use App\Models\Announcement;
use App\Models\Classroom;
use App\Models\SchoolYear;
use App\Services\Communications\CommunicationService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CommunicationAnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Announcement::class);
        $user = $request->user();

        $announcements = Announcement::query()
            ->with(['author', 'classroom', 'schoolYear'])
            ->withExists(['recipients as is_read' => fn ($recipients) => $recipients
                ->whereKey($user->id)
                ->whereNotNull('announcement_user.read_at')])
            ->when($user->isGuardian(), fn ($query) => $query->whereHas(
                'recipients',
                fn ($recipients) => $recipients->whereKey($user->id),
            ))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('communications.announcements.index', compact('announcements'));
    }

    public function create(): View
    {
        Gate::authorize('create', Announcement::class);
        $schoolYear = SchoolYear::current();
        $classrooms = Classroom::query()
            ->where('status', ClassroomStatus::Active)
            ->when($schoolYear, fn ($query) => $query->where('school_year_id', $schoolYear->id))
            ->orderBy('name')
            ->get();

        return view('communications.announcements.create', compact('classrooms', 'schoolYear'));
    }

    public function store(StoreAnnouncementRequest $request, CommunicationService $communications): RedirectResponse
    {
        try {
            $announcement = $communications->publishAnnouncement(
                $request->safe()->only(['title', 'body', 'classroom_id']),
                $request->user(),
            );
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['audience' => $exception->getMessage()]);
        }

        return redirect()
            ->route('communications.announcements.show', $announcement)
            ->with('success', 'L’annonce a été publiée auprès des familles concernées.');
    }

    public function show(Request $request, Announcement $announcement): View
    {
        abort_if(
            $request->user()->isGuardian()
                && ! $announcement->recipients()->whereKey($request->user()->id)->exists(),
            404,
        );
        Gate::authorize('view', $announcement);

        if ($request->user()->isGuardian()) {
            $announcement->recipients()->updateExistingPivot($request->user()->id, ['read_at' => now()]);
        }

        $announcement->load(['author', 'classroom', 'schoolYear']);

        return view('communications.announcements.show', compact('announcement'));
    }
}
