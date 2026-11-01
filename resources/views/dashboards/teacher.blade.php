<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wider text-teal-700">Espace enseignant</p>
            <h2 class="mt-1 text-2xl font-bold text-slate-900">Bonjour, {{ auth()->user()->name }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $schoolYear ? 'Année scolaire '.$schoolYear->label : 'Aucune année scolaire active' }}</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
            <section aria-label="Mon activité" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-dashboard.stat-card label="Classes suivies" :value="$classCount" />
                <x-dashboard.stat-card label="Élèves inscrits dans mes classes" :value="$studentCount" />
            </section>

            <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-slate-900">Mes séances aujourd’hui</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ now()->locale(config('app.locale'))->translatedFormat('l d F') }}</p>
                        </div>
                        <a href="{{ route('teacher.schedules.index') }}" class="text-sm font-semibold text-teal-800 hover:underline">Emploi du temps</a>
                    </div>
                    <div class="mt-4 divide-y divide-slate-100">
                        @forelse ($todaySchedules as $schedule)
                            <div class="flex items-start justify-between gap-4 py-3">
                                <div>
                                    <p class="font-medium text-slate-900">{{ $schedule->assignment->subject->name }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ $schedule->classroom->name }} · {{ $schedule->room->name }}</p>
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
                            <h3 class="font-semibold text-slate-900">Mes classes et matières</h3>
                            <p class="mt-1 text-sm text-slate-500">Affectations pour l’année active</p>
                        </div>
                        <a href="{{ route('attendance.create') }}" class="rounded-lg bg-teal-800 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-700">Faire l’appel</a>
                    </div>
                    <div class="mt-4 divide-y divide-slate-100">
                        @forelse ($assignments as $assignment)
                            <div class="py-3">
                                <p class="font-medium text-slate-900">{{ $assignment->classroom->name }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $assignment->subject->name }}</p>
                            </div>
                        @empty
                            <p class="py-8 text-center text-sm text-slate-500">Aucune affectation active pour le moment.</p>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-slate-900">Feuilles d’appel récentes</h3>
                        <p class="mt-1 text-sm text-slate-500">Séances prises aujourd’hui</p>
                    </div>
                    <a href="{{ route('attendance.index') }}" class="text-sm font-semibold text-teal-800 hover:underline">Toutes mes séances</a>
                </div>
                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse ($todaySessions as $session)
                        <a href="{{ route('attendance.show', $session) }}" class="rounded-xl bg-slate-50 p-4 transition hover:bg-teal-50">
                            <p class="font-medium text-slate-900">{{ $session->classroom->name }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ $session->isDaily() ? 'Appel quotidien' : $session->subject?->name }} · {{ $session->startTime() }}</p>
                        </a>
                    @empty
                        <p class="py-4 text-sm text-slate-500">Aucune feuille d’appel enregistrée aujourd’hui.</p>
                    @endforelse
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <div>
                        <h3 class="font-semibold text-slate-900">Dernières notes saisies</h3>
                        <p class="mt-1 text-sm text-slate-500">Vos notes enregistrées pour l’année scolaire active</p>
                    </div>
                    <a href="{{ route('grades.index') }}" class="text-sm font-semibold text-teal-800 hover:underline">Voir mes évaluations</a>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse ($recentGrades as $grade)
                        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                            <div>
                                <p class="font-medium text-slate-900">{{ $grade->enrollment->student->full_name }}</p>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ $grade->evaluation->classroom->name }} · {{ $grade->evaluation->assignment->subject->name }}
                                    · {{ $grade->evaluation->title }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-slate-900">{{ number_format((float) $grade->score, 2, ',', ' ') }} / 20</p>
                                <p class="mt-1 text-xs text-slate-500">{{ \App\Support\Format::dateTime($grade->updated_at) }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="px-5 py-8 text-center text-sm text-slate-500">Aucune note saisie récemment pour l’année scolaire active.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
