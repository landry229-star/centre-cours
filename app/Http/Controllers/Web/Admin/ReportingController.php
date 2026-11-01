<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\SchoolYear;
use App\Services\Grades\ReportCardService;
use App\Services\Reporting\SchoolYearReportService;
use App\Support\Format;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportingController extends Controller
{
    public function index(Request $request, ReportCardService $periods, SchoolYearReportService $reports): View
    {
        Gate::authorize('viewAny', SchoolYear::class);
        $data = $this->reportData($request, $periods, $reports);

        return view('admin.reports.index', $data);
    }

    public function export(
        Request $request,
        ReportCardService $periods,
        SchoolYearReportService $reports,
    ): StreamedResponse|RedirectResponse {
        Gate::authorize('viewAny', SchoolYear::class);
        $data = $this->reportData($request, $periods, $reports);

        if ($data['schoolYear'] === null) {
            return redirect()
                ->route('admin.reports.index')
                ->with('error', 'Créez une année scolaire avant de télécharger un rapport.');
        }

        $rows = $data['report']['classes'];
        $periodLabel = $data['selectedPeriod']['label'];
        $schoolYearLabel = $data['schoolYear']->label;
        $studentsWithoutFeeSchedule = $data['report']['totals']['students_without_fee_schedule'];

        return response()->streamDownload(function () use (
            $rows,
            $periodLabel,
            $schoolYearLabel,
            $studentsWithoutFeeSchedule,
        ): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            $spreadsheetSafe = static fn (string $value): string => preg_match('/^[\t\r\n ]*[=+\-@]/', $value) === 1
                ? "'".$value
                : $value;
            fputcsv($stream, [
                'Année scolaire',
                'Période',
                'Classe',
                'État',
                'Effectif à la fin de la période',
                'Présents',
                'Retards',
                'Absences',
                'Absences justifiées',
                'Pointages',
                'Moyenne des notes publiées / 20',
                'Notes publiées',
                'Élèves avec notes',
                'Échéances dues (FCFA)',
                'Payé sur échéances dues (FCFA)',
                'Reste dû (FCFA)',
                'Élèves avec arriérés',
                'Élèves sans tarif configuré pour l’année',
            ], ';', '"', '');

            foreach ($rows as $row) {
                fputcsv($stream, [
                    $spreadsheetSafe($schoolYearLabel),
                    $periodLabel,
                    $spreadsheetSafe($row['name']),
                    $row['status'],
                    $row['enrollments'],
                    $row['attendance']['present'],
                    $row['attendance']['late'],
                    $row['attendance']['absent'],
                    $row['attendance']['excused'],
                    $row['attendance_total'],
                    $row['grades_average'] === null ? '' : number_format($row['grades_average'], 2, '.', ''),
                    $row['grades_count'],
                    $row['grades_students'],
                    $row['expected_due'],
                    $row['paid_due'],
                    $row['remaining_due'],
                    $row['arrears_students'],
                    $studentsWithoutFeeSchedule,
                ], ';', '"', '');
            }

            fclose($stream);
        }, 'rapport-'.str($data['schoolYear']->label)->slug().'-'.str($periodLabel)->slug().'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function reportData(
        Request $request,
        ReportCardService $periods,
        SchoolYearReportService $reports,
    ): array {
        $filters = $request->validate([
            'school_year_id' => ['nullable', 'integer', Rule::exists('school_years', 'id')],
            'classroom_id' => ['nullable', 'integer'],
            'period' => ['nullable', 'string', 'regex:/^(trimester|semester|annual):[1-3]$/'],
        ]);
        $schoolYearId = $filters['school_year_id'] ?? SchoolYear::current()?->id
            ?? SchoolYear::query()->orderByDesc('start_date')->value('id');
        $schoolYear = $schoolYearId === null ? null : SchoolYear::query()->findOrFail($schoolYearId);

        if ($schoolYear === null) {
            return [
                'schoolYear' => null,
                'schoolYears' => SchoolYear::query()->orderByDesc('start_date')->pluck('label', 'id')->all(),
                'classrooms' => collect(),
                'periodOptions' => [],
                'selectedPeriod' => null,
                'filters' => [],
                'report' => [
                    'classes' => collect(),
                    'totals' => [
                        'enrollments' => 0,
                        'attendance' => array_fill_keys(['present', 'late', 'absent', 'excused'], 0),
                        'attendance_total' => 0,
                        'grades_count' => 0,
                        'grades_students' => 0,
                        'grades_average' => null,
                        'expected_due' => 0,
                        'paid_due' => 0,
                        'remaining_due' => 0,
                        'classes_with_arrears' => 0,
                        'students_without_fee_schedule' => 0,
                    ],
                ],
            ];
        }

        if (isset($filters['classroom_id'])
            && ! Classroom::withTrashed()
                ->where('school_year_id', $schoolYear->id)
                ->whereKey($filters['classroom_id'])
                ->exists()) {
            throw ValidationException::withMessages([
                'classroom_id' => 'La classe sélectionnée ne fait pas partie de cette année scolaire.',
            ]);
        }

        $availablePeriods = $periods->periods($schoolYear);
        $periodKey = $filters['period'] ?? 'annual:1';
        [$periodType, $periodNumber] = explode(':', $periodKey);
        $selectedPeriod = collect($availablePeriods)->first(
            fn (array $period): bool => $period['type'] === $periodType
                && $period['number'] === (int) $periodNumber,
        );

        if ($selectedPeriod === null) {
            throw ValidationException::withMessages([
                'period' => 'La période sélectionnée n’existe pas pour cette année scolaire.',
            ]);
        }

        $classrooms = Classroom::withTrashed()
            ->where('school_year_id', $schoolYear->id)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
        $periodOptions = collect($availablePeriods)
            ->mapWithKeys(fn (array $period): array => [
                $period['type'].':'.$period['number'] => $period['label']
                    .' ('.Format::date($period['start']).' – '.Format::date($period['end']).')',
            ])
            ->all();
        $report = $reports->generate(
            $schoolYear,
            $selectedPeriod['start'],
            $selectedPeriod['end'],
            isset($filters['classroom_id']) ? (int) $filters['classroom_id'] : null,
        );

        return [
            'schoolYear' => $schoolYear,
            'schoolYears' => SchoolYear::query()->orderByDesc('start_date')->pluck('label', 'id')->all(),
            'classrooms' => $classrooms,
            'periodOptions' => $periodOptions,
            'selectedPeriod' => $selectedPeriod,
            'filters' => [
                'school_year_id' => $schoolYear->id,
                'classroom_id' => $filters['classroom_id'] ?? '',
                'period' => $periodKey,
            ],
            'report' => $report,
        ];
    }
}
