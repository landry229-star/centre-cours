<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wider text-teal-700">Espace élève</p>
            <h2 class="mt-1 text-2xl font-bold text-slate-900">Bonjour, {{ auth()->user()->name }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $schoolYear ? 'Année scolaire '.$schoolYear->label : 'Aucune année scolaire active' }}</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
            <section aria-label="Mon résumé scolaire" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-dashboard.stat-card label="Classe" :value="$enrollments->first()?->classroom?->name ?? 'Non attribuée'" />
                <x-dashboard.stat-card label="Absences cette année" :value="$absenceCount" />
                <x-dashboard.stat-card label="Moyenne générale" :value="$summaries->first() ? number_format($summaries->first()['average'], 2, ',', ' ').' / 20' : 'Pas encore de note'" />
            </section>

            <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-slate-900">Mes cours aujourd’hui</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ now()->locale(config('app.locale'))->translatedFormat('l d F') }}</p>
                        </div>
                        <a href="{{ route('student.schedules.index') }}" class="text-sm font-semibold text-teal-800 hover:underline">Emploi du temps</a>
                    </div>
                    <div class="mt-4 divide-y divide-slate-100">
                        @forelse ($todaySchedules as $schedule)
                            <div class="flex items-start justify-between gap-4 py-3">
                                <div>
                                    <p class="font-medium text-slate-900">{{ $schedule->assignment->subject->name }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ $schedule->assignment->teacher->full_name }} · {{ $schedule->room->name }}</p>
                                </div>
                                <span class="whitespace-nowrap text-sm font-semibold text-slate-700">{{ $schedule->startTime() }}–{{ $schedule->endTime() }}</span>
                            </div>
                        @empty
                            <p class="py-8 text-center text-sm text-slate-500">Aucun cours planifié aujourd’hui.</p>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-slate-900">Mes dernières notes</h3>
                            <p class="mt-1 text-sm text-slate-500">Résultats publiés</p>
                        </div>
                        <a href="{{ route('student.grades.index') }}" class="text-sm font-semibold text-teal-800 hover:underline">Toutes mes notes</a>
                    </div>
                    <div class="mt-4 divide-y divide-slate-100">
                        @forelse ($recentGrades as $grade)
                            <div class="flex items-center justify-between gap-4 py-3">
                                <div>
                                    <p class="font-medium text-slate-900">{{ $grade->evaluation->assignment->subject->name }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ $grade->evaluation->title }}</p>
                                </div>
                                <span class="font-semibold text-teal-800">{{ number_format((float) $grade->score, 2, ',', ' ') }} / 20</span>
                            </div>
                        @empty
                            <p class="py-8 text-center text-sm text-slate-500">Aucune note publiée pour le moment.</p>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
