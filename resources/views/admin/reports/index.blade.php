<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wider text-teal-700">Administration</p>
            <h2 class="mt-1 text-2xl font-bold text-slate-900">Synthèse de pilotage</h2>
            <p class="mt-1 text-sm text-slate-500">
                Effectifs, présence, résultats publiés et échéances financières par classe.
            </p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-flash />

            @if ($schoolYear)
                <form method="GET" action="{{ route('admin.reports.index') }}" class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-4">
                    <label class="grid gap-1 text-sm font-medium text-slate-700">
                        <span>Année scolaire</span>
                        <select name="school_year_id" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                            @foreach ($schoolYears as $id => $label)
                                <option value="{{ $id }}" @selected((string) $filters['school_year_id'] === (string) $id)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('school_year_id')" />
                    </label>
                    <label class="grid gap-1 text-sm font-medium text-slate-700">
                        <span>Classe</span>
                        <select name="classroom_id" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                            <option value="">Toutes les classes</option>
                            @foreach ($classrooms as $id => $name)
                                <option value="{{ $id }}" @selected((string) $filters['classroom_id'] === (string) $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('classroom_id')" />
                    </label>
                    <label class="grid gap-1 text-sm font-medium text-slate-700">
                        <span>Période</span>
                        <select name="period" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                            @foreach ($periodOptions as $key => $label)
                                <option value="{{ $key }}" @selected($filters['period'] === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('period')" />
                    </label>
                    <div class="flex items-end gap-2">
                        <x-primary-button>Appliquer</x-primary-button>
                        <a href="{{ route('admin.reports.index') }}" class="inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-medium text-slate-600 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-teal-600">
                            Réinitialiser
                        </a>
                    </div>
                </form>

                @if ($report['totals']['students_without_fee_schedule'] > 0)
                    <div role="status" class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
                        Les soldes sont incomplets : aucun tarif annuel n’est configuré pour
                        {{ $report['totals']['students_without_fee_schedule'] }} élève(s). Vérifiez les tarifs avant d’utiliser ces chiffres.
                    </div>
                @endif

                <section aria-label="Indicateurs pour la période sélectionnée" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <x-dashboard.stat-card label="Effectif en fin de période" :value="$report['totals']['enrollments']" :hint="$selectedPeriod['label']" />
                    <x-dashboard.stat-card label="Absences enregistrées" :value="$report['totals']['attendance']['absent']" hint="Les absences justifiées sont comptées à part." />
                    <x-dashboard.stat-card label="Moyenne publiée par élève" :value="$report['totals']['grades_average'] === null ? '—' : number_format($report['totals']['grades_average'], 2, ',', ' ')" hint="{{ $report['totals']['grades_students'] }} élève(s), {{ $report['totals']['grades_count'] }} note(s), coefficients appliqués." />
                    <x-dashboard.stat-card label="Reste dû sur échéances exigibles" :value="\App\Support\Format::fcfa($report['totals']['remaining_due'])" hint="{{ $report['totals']['classes_with_arrears'] }} classe(s) avec arriérés." />
                </section>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-slate-600">
                        Année {{ $schoolYear->label }} · {{ \App\Support\Format::date($selectedPeriod['start']) }} – {{ \App\Support\Format::date($selectedPeriod['end']) }}.
                        L’effectif est photographié à la fin de la période; les présences et notes sont celles enregistrées dans ses dates.
                        Les échéances suivent le calcul financier de l’année scolaire et ne retiennent que les mensualités exigibles à ce jour.
                    </p>
                    <a href="{{ route('admin.reports.export', request()->query()) }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-teal-600">
                        Exporter CSV
                    </a>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <caption class="sr-only">Indicateurs par classe pour {{ $schoolYear->label }} — {{ $selectedPeriod['label'] }}</caption>
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th scope="col" class="px-4 py-3">Classe</th>
                                    <th scope="col" class="px-4 py-3">Effectif</th>
                                    <th scope="col" class="px-4 py-3">Présence</th>
                                    <th scope="col" class="px-4 py-3">Résultats pondérés</th>
                                    <th scope="col" class="px-4 py-3">Paiements exigibles</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($report['classes'] as $class)
                                    <tr class="align-top">
                                        <th scope="row" class="whitespace-nowrap px-4 py-4 text-left font-semibold text-slate-900">
                                            {{ $class['name'] }}
                                            @if ($class['status'] !== 'Ouverte')
                                                <span class="mt-1 block text-xs font-normal text-slate-500">{{ $class['status'] }}</span>
                                            @endif
                                        </th>
                                        <td class="px-4 py-4 font-medium text-slate-800">{{ $class['enrollments'] }}</td>
                                        <td class="min-w-48 px-4 py-4 text-slate-700">
                                            <dl class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
                                                <dt>Présents</dt><dd>{{ $class['attendance']['present'] }}</dd>
                                                <dt>Retards</dt><dd>{{ $class['attendance']['late'] }}</dd>
                                                <dt>Absents</dt><dd>{{ $class['attendance']['absent'] }}</dd>
                                                <dt>Justifiés</dt><dd>{{ $class['attendance']['excused'] }}</dd>
                                            </dl>
                                            <p class="mt-2 text-xs text-slate-500">{{ $class['attendance_total'] }} pointage(s)</p>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-4 text-slate-700">
                                            @if ($class['grades_average'] === null)
                                                <span class="text-slate-500">Aucune note publiée</span>
                                            @else
                                                <span class="font-semibold">{{ number_format($class['grades_average'], 2, ',', ' ') }} / 20</span>
                                            <span class="mt-1 block text-xs text-slate-500">{{ $class['grades_students'] }} élève(s), {{ $class['grades_count'] }} note(s)</span>
                                            @endif
                                        </td>
                                        <td class="min-w-56 px-4 py-4 text-slate-700">
                                            <dl class="grid grid-cols-[1fr_auto] gap-x-3 gap-y-1 text-xs">
                                                <dt>Attendu</dt><dd>{{ \App\Support\Format::fcfa($class['expected_due']) }}</dd>
                                                <dt>Payé</dt><dd>{{ \App\Support\Format::fcfa($class['paid_due']) }}</dd>
                                                <dt class="font-semibold">Reste</dt><dd class="font-semibold">{{ \App\Support\Format::fcfa($class['remaining_due']) }}</dd>
                                            </dl>
                                            <p class="mt-2 text-xs text-slate-500">{{ $class['arrears_students'] }} élève(s) avec au moins une échéance impayée.</p>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-10 text-center">
                                            <p class="font-medium text-slate-800">Aucune classe à afficher pour ces filtres.</p>
                                            <p class="mt-1 text-sm text-slate-500">Choisissez une autre année ou créez une classe pour cette année scolaire.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <section class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center">
                    <h3 class="font-semibold text-slate-900">Aucune année scolaire disponible</h3>
                    <p class="mt-2 text-sm text-slate-600">Créez une année scolaire et configurez ses périodes pour consulter les indicateurs.</p>
                    <a href="{{ route('admin.school-years.create') }}" class="mt-4 inline-flex min-h-10 items-center justify-center rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2">
                        Créer une année scolaire
                    </a>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
