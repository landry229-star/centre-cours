<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bulletin scolaire</title>
    <style>
        @page { margin: 32px 38px; }
        body { color: #1e293b; font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        h1, h2, h3, p { margin: 0; }
        .centre-header { margin-bottom: 12px; }
        .centre-logo { height: 48px; margin-right: 10px; max-width: 90px; }
        .centre { color: #0f766e; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .title { margin-top: 12px; font-size: 21px; }
        .subtitle { color: #64748b; margin-top: 5px; }
        .student { border: 1px solid #cbd5e1; margin-top: 20px; padding: 12px; }
        .student table, .grades { border-collapse: collapse; width: 100%; }
        .student td { padding: 3px 8px 3px 0; }
        .summary { background: #f0fdfa; margin-top: 16px; padding: 12px; }
        .summary td { font-size: 13px; font-weight: bold; padding-right: 24px; }
        .subject { margin-top: 18px; }
        .subject h3 { background: #f1f5f9; font-size: 12px; padding: 8px; }
        .grades th, .grades td { border-bottom: 1px solid #e2e8f0; padding: 7px 6px; text-align: left; }
        .grades th { color: #475569; font-size: 9px; text-transform: uppercase; }
        .score { text-align: right !important; white-space: nowrap; }
        .empty { color: #64748b; margin-top: 24px; text-align: center; }
        .footer { border-top: 1px solid #cbd5e1; color: #64748b; margin-top: 28px; padding-top: 8px; }
    </style>
</head>
<body>
    <table class="centre-header">
        <tr>
            @if ($logo)
                <td><img class="centre-logo" src="{{ $logo }}" alt=""></td>
            @endif
            <td>
                <p class="centre">{{ $centre['name'] }}</p>
                <p>{{ $centre['address'] }}</p>
                <p>{{ $centre['phone'] }}@if ($centre['email']) · {{ $centre['email'] }}@endif</p>
            </td>
        </tr>
    </table>
    <h1 class="title">Bulletin scolaire — {{ $report['period']['label'] }}</h1>
    <p class="subtitle">
        Période du {{ \App\Support\Format::date($report['period']['start']) }} au {{ \App\Support\Format::date($report['period']['end']) }}
    </p>

    <section class="student">
        <table>
            <tr>
                <td><strong>Élève</strong></td>
                <td>{{ $report['enrollment']->student->full_name }}</td>
                <td><strong>Matricule</strong></td>
                <td>{{ $report['enrollment']->student->matricule }}</td>
            </tr>
            <tr>
                <td><strong>Classe</strong></td>
                <td>{{ $report['enrollment']->classroom->name }} — {{ $report['enrollment']->classroom->level->name }}</td>
                <td><strong>Année scolaire</strong></td>
                <td>{{ $report['enrollment']->schoolYear->label }}</td>
            </tr>
        </table>
    </section>

    <section class="summary">
        <table>
            <tr>
                <td>Moyenne générale</td>
                <td>{{ $report['average'] === null ? '—' : number_format($report['average'], 2, ',', ' ').' / 20' }}</td>
                <td>Rang de classe</td>
                <td>{{ $report['rank'] === null ? '—' : $report['rank'].' / '.$report['class_size'] }}</td>
            </tr>
        </table>
    </section>

    @forelse ($report['subjects'] as $subject)
        <section class="subject">
            <h3>{{ $subject['name'] }} · Moyenne {{ number_format($subject['average'], 2, ',', ' ') }} / 20 · Coefficient {{ $subject['coefficient'] }}</h3>
            <table class="grades">
                <thead>
                    <tr>
                        <th>Évaluation</th>
                        <th>Date</th>
                        <th class="score">Note</th>
                        <th>Appréciation</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($subject['grades'] as $grade)
                        <tr>
                            <td>{{ $grade->evaluation->title }}</td>
                            <td>{{ \App\Support\Format::date($grade->evaluation->evaluation_date) }}</td>
                            <td class="score">{{ number_format((float) $grade->score, 2, ',', ' ') }} / 20</td>
                            <td>{{ $grade->comment ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @empty
        <p class="empty">Aucune note saisie pour cette période.</p>
    @endforelse

    <p class="footer">Document généré le {{ \App\Support\Format::date(now()) }} · {{ $centre['name'] }}</p>
</body>
</html>
