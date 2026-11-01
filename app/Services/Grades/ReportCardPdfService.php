<?php

namespace App\Services\Grades;

use App\Services\CentreSettingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Str;

class ReportCardPdfService
{
    public function __construct(private CentreSettingsService $centreSettings) {}

    /** @param array<string, mixed> $report */
    public function make(array $report): DomPdf
    {
        return Pdf::loadView('report-cards.pdf', [
            'report' => $report,
            'centre' => $this->centreSettings->current(),
            'logo' => $this->centreSettings->logoDataUri(),
        ])->setPaper('a4', 'portrait');
    }

    /** @param array<string, mixed> $report */
    public function filename(array $report): string
    {
        $student = $report['enrollment']->student;
        $period = Str::slug($report['period']['label']);

        return 'bulletin-'.$student->matricule.'-'.$period.'.pdf';
    }
}
