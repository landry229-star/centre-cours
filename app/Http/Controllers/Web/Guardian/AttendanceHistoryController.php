<?php

namespace App\Http\Controllers\Web\Guardian;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Student;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceHistoryController extends Controller
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('viewAny', Attendance::class);

        $guardianId = $request->user()->guardian->id;
        $filters = $this->validatedFilters($request);
        $query = $this->attendanceQuery($guardianId, $filters);
        $counts = (clone $query)
            ->reorder()
            ->select([])
            ->selectRaw('attendances.status, COUNT(*) as total')
            ->groupBy('attendances.status')
            ->toBase()
            ->pluck('total', 'status');
        $summary = [];

        foreach (AttendanceStatus::cases() as $status) {
            $summary[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        $summary['total'] = array_sum($summary);

        $attendances = $query
            ->with([
                'enrollment.student' => fn (BelongsTo $query) => $query->withTrashed(),
                'session.classroom',
                'session.subject',
            ])
            ->paginate(25)
            ->withQueryString();

        return view('guardian.attendance.index', [
            'attendances' => $attendances,
            'summary' => $summary,
            'statuses' => AttendanceStatus::cases(),
            'filters' => $filters,
            'students' => Student::withTrashed()
                ->where('guardian_id', $guardianId)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Attendance::class);

        $filters = $this->validatedFilters($request);
        $rows = $this->attendanceQuery($request->user()->guardian->id, $filters)
            ->with([
                'enrollment.student' => fn (BelongsTo $query) => $query->withTrashed(),
                'session.classroom',
                'session.subject',
            ])
            ->reorder()
            ->orderBy('attendances.id');

        return response()->streamDownload(function () use ($rows) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Date', 'Élève', 'Classe', 'Séance', 'Statut', 'Arrivée', 'Remarque'], ';', '"', '');

            $rows->chunkById(500, function ($attendances) use ($stream) {
                foreach ($attendances as $attendance) {
                    $session = $attendance->session;
                    fputcsv($stream, [
                        $session->session_date->toDateString(),
                        $attendance->enrollment->student->full_name,
                        $session->classroom->name,
                        $session->isDaily() ? 'Appel quotidien' : $session->subject->name,
                        $attendance->status->label(),
                        $attendance->arrivalTime(),
                        $attendance->note,
                    ], ';', '"', '');
                }
            }, 'attendances.id', 'id');

            fclose($stream);
        }, 'presences-enfants-'.now()->toDateString().'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{student_id?: string, from?: string, to?: string, status?: string}
     */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'student_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', Rule::enum(AttendanceStatus::class)],
        ]);
    }

    /**
     * @param  array{student_id?: string, from?: string, to?: string, status?: string}  $filters
     */
    private function attendanceQuery(int $guardianId, array $filters): Builder
    {
        return Attendance::query()
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendances.attendance_session_id')
            ->whereHas('enrollment.student', fn (Builder $query) => $query
                ->withTrashed()
                ->where('guardian_id', $guardianId)
                ->when($filters['student_id'] ?? null, fn (Builder $query, string $studentId) => $query->whereKey($studentId)))
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->where('attendance_sessions.session_date', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->where('attendance_sessions.session_date', '<=', $date))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('attendances.status', $status))
            ->select('attendances.*')
            ->orderByDesc('attendance_sessions.session_date')
            ->orderByDesc('attendance_sessions.starts_at')
            ->orderByDesc('attendances.id');
    }
}
