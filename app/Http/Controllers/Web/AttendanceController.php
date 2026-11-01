<?php

namespace App\Http\Controllers\Web;

use App\Enums\AttendanceSessionType;
use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceRecordRequest;
use App\Http\Requests\AttendanceSessionRequest;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceSession;
use App\Models\SchoolYear;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Présences : partagé entre l'admin et les enseignants (routes /presences).
 * Les Policies décident : un enseignant n'accède qu'aux séances de ses propres classes / matières.
 */
class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AttendanceSession::class);
        $user = $request->user();

        $filters = $this->validatedFilters($request);
        $query = $this->sessionsQuery($user, $filters);
        $counts = Attendance::query()
            ->whereIn('attendance_session_id', (clone $query)->select('id'))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->toBase()
            ->pluck('total', 'status');
        $summary = [];

        foreach (AttendanceStatus::cases() as $status) {
            $summary[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        $summary['total'] = array_sum($summary);
        $summary['sessions'] = (clone $query)->count();

        $sessions = $query
            ->with(['classroom', 'subject', 'teacher'])
            ->withCount([
                'attendances as present_count' => fn ($q) => $q->where('status', 'present'),
                'attendances as late_count' => fn ($q) => $q->where('status', 'late'),
                'attendances as absent_count' => fn ($q) => $q->where('status', 'absent'),
                'attendances as excused_count' => fn ($q) => $q->where('status', 'excused'),
            ])
            ->orderByDesc('session_date')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('attendance.index', [
            'sessions' => $sessions,
            'summary' => $summary,
            'statuses' => AttendanceStatus::cases(),
            'filters' => $filters,
            'classrooms' => AttendanceSession::query()
                ->visibleTo($user)
                ->join('classrooms', 'classrooms.id', '=', 'attendance_sessions.classroom_id')
                ->select('classrooms.id', 'classrooms.name')
                ->distinct()
                ->orderBy('classrooms.name')
                ->pluck('classrooms.name', 'classrooms.id')
                ->all(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', AttendanceSession::class);
        $filters = $this->validatedFilters($request);
        $rows = Attendance::query()
            ->with([
                'session.classroom',
                'session.subject',
                'session.teacher',
                'enrollment.student' => fn (BelongsTo $query) => $query->withTrashed(),
                'marker',
            ])
            ->whereHas('session', function (Builder $query) use ($request, $filters) {
                $this->applySessionFilters($query->visibleTo($request->user()), $filters);
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->reorder()
            ->orderBy('attendances.id');

        return response()->streamDownload(function () use ($rows) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Date', 'Début', 'Classe', 'Séance', 'Enseignant', 'Élève', 'Matricule', 'Statut', 'Arrivée', 'Remarque', 'Saisi par'], ';', '"', '');

            $rows->chunkById(500, function ($attendances) use ($stream) {
                foreach ($attendances as $attendance) {
                    $session = $attendance->session;
                    fputcsv($stream, [
                        $session->session_date->toDateString(),
                        $session->startTime(),
                        $session->classroom->name,
                        $session->isDaily() ? 'Appel quotidien' : $session->subject->name,
                        $session->teacher->full_name,
                        $attendance->enrollment->student->full_name,
                        $attendance->enrollment->student->matricule,
                        $attendance->status->label(),
                        $attendance->arrivalTime(),
                        $attendance->note,
                        $attendance->marker->name,
                    ], ';', '"', '');
                }
            }, 'attendances.id', 'id');

            fclose($stream);
        }, 'presences-'.now()->toDateString().'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('viewAny', AttendanceSession::class);
        $assignments = $this->assignmentsFor($request);

        return view('attendance.create', [
            'assignments' => $assignments,
            'dailyAssignments' => $assignments->unique('classroom_id')->values(),
        ]);
    }

    public function store(AttendanceSessionRequest $request, AttendanceService $service): RedirectResponse
    {
        $assignment = TeachingAssignment::with(['classroom.schoolYear', 'subject'])->findOrFail($request->validated('assignment_id'));

        Gate::authorize('openFor', [AttendanceSession::class, $assignment]);

        try {
            $session = $service->openSession(
                $assignment,
                AttendanceSessionType::from($request->validated('session_type')),
                Carbon::parse($request->validated('date')),
                $request->validated('starts_at') ?? '00:00',
                $request->validated('ends_at'),
                $request->user(),
            );
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('attendance.show', $session)
            ->with('success', $session->wasRecentlyCreated
                ? ($session->isDaily() ? 'Appel quotidien créé.' : 'Séance créée. Pointez les élèves ci-dessous.')
                : ($session->isDaily() ? 'Cet appel quotidien existe déjà : le voici.' : 'Cette séance existe déjà : la voici.'));
    }

    public function show(AttendanceSession $attendanceSession, AttendanceService $service): View
    {
        Gate::authorize('view', $attendanceSession);

        $attendanceSession->load(['classroom.level', 'subject', 'teacher']);

        return view('attendance.show', [
            'session' => $attendanceSession,
            'roster' => $service->roster($attendanceSession),
            'records' => Attendance::where('attendance_session_id', $attendanceSession->id)
                ->get()
                ->keyBy('enrollment_id'),
            'corrections' => AttendanceCorrection::query()
                ->whereHas('attendance', fn (Builder $query) => $query->where('attendance_session_id', $attendanceSession->id))
                ->with(['attendance.enrollment.student', 'corrector'])
                ->orderByDesc('corrected_at')
                ->orderByDesc('id')
                ->get(),
            'summary' => $service->summary($attendanceSession),
            'statuses' => AttendanceStatus::cases(),
        ]);
    }

    public function update(AttendanceRecordRequest $request, AttendanceSession $attendanceSession, AttendanceService $service): RedirectResponse
    {
        try {
            $summary = $service->record($attendanceSession, $request->validated('entries'), $request->user());
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('attendance.show', $attendanceSession)
            ->with('success', "Présences enregistrées : {$summary['present']} présent(s), {$summary['late']} en retard, {$summary['absent']} absent(s), {$summary['excused']} justifié(s).");
    }

    /**
     * @return array{classroom_id?: string, from?: string, to?: string, status?: string}
     */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'classroom_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', Rule::enum(AttendanceStatus::class)],
        ]);
    }

    /**
     * @param  array{classroom_id?: string, from?: string, to?: string, status?: string}  $filters
     */
    private function sessionsQuery(User $user, array $filters): Builder
    {
        return $this->applySessionFilters(AttendanceSession::query()->visibleTo($user), $filters);
    }

    /**
     * @param  array{classroom_id?: string, from?: string, to?: string, status?: string}  $filters
     */
    private function applySessionFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['classroom_id'] ?? null, fn (Builder $query, string $id) => $query->where('classroom_id', $id))
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->where('session_date', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->where('session_date', '<=', $date))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->whereHas(
                'attendances',
                fn (Builder $attendances) => $attendances->where('status', $status),
            ));
    }

    /** Pointage par QR Code : formulaire classique (lecteur USB/Bluetooth) ou JSON (caméra du navigateur). */
    public function scan(Request $request, AttendanceSession $attendanceSession, AttendanceService $service): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $attendanceSession);

        $data = $request->validate(['token' => ['required', 'string', 'max:100']]);

        try {
            $result = $service->markByQr($attendanceSession, $data['token'], $request->user());
        } catch (DomainException $e) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $e->getMessage()], 422)
                : back()->with('error', $e->getMessage());
        }

        $name = $result['student']->full_name;
        $message = match (true) {
            $result['already'] => "{$name} était déjà pointé(e) : {$result['status']->label()}.",
            default => "{$name} : {$result['status']->label()}.",
        };

        return $request->expectsJson()
            ? response()->json([
                'ok' => true,
                'message' => $message,
                'status' => $result['status']->value,
                'enrollment_id' => $result['enrollment_id'],
                'already' => $result['already'],
            ])
            : back()->with('success', $message);
    }

    /**
     * Affectations proposées pour ouvrir une séance : celles de l'enseignant connecté, ou toutes (admin),
     * limitées aux classes actives de l'année scolaire en cours.
     *
     * @return Collection<int, TeachingAssignment>
     */
    private function assignmentsFor(Request $request)
    {
        $user = $request->user();

        return TeachingAssignment::query()
            ->with(['classroom', 'subject', 'teacher'])
            ->whereHas('classroom', fn ($c) => $c
                ->where('status', 'active')
                ->whereIn('school_year_id', SchoolYear::active()->select('id')))
            ->when(! $user->isAdmin(), fn ($q) => $q->where('teacher_id', $user->teacher?->id ?? 0))
            ->get()
            ->sortBy(fn (TeachingAssignment $a) => $a->classroom->name.' '.$a->subject->name)
            ->values();
    }
}
