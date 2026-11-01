<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wider text-teal-700">Espace famille</p>
            <h2 class="mt-1 text-2xl font-bold text-slate-900">Bonjour, {{ auth()->user()->name }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $schoolYear ? 'Suivi de l’année '.$schoolYear->label : 'Aucune année scolaire active' }}</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
            <section aria-label="Résumé familial" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-dashboard.stat-card label="Enfants suivis" :value="$children->count()" />
                <x-dashboard.stat-card label="Absences sur l’année active" :value="$absenceCount" />
            </section>

            <section>
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Mes enfants</h3>
                        <p class="mt-1 text-sm text-slate-500">Accès rapide à leur suivi scolaire</p>
                    </div>
                    <div class="flex flex-wrap gap-3 text-sm font-semibold text-teal-800">
                        <a href="{{ route('guardian.attendance.index') }}" class="hover:underline">Présences</a>
                        <a href="{{ route('guardian.grades.index') }}" class="hover:underline">Notes</a>
                        <a href="{{ route('guardian.payments.index') }}" class="hover:underline">Paiements et échéancier</a>
                        <a href="{{ route('guardian.schedules.index') }}" class="hover:underline">Emploi du temps</a>
                        <a href="{{ route('communications.announcements.index') }}" class="hover:underline">Annonces</a>
                        <a href="{{ route('communications.messages.index') }}" class="hover:underline">Messages</a>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @forelse ($children as $child)
                        @php($enrollment = $child->enrollments->first())
                        @php($summary = $summaries->firstWhere('student.id', $child->id))
                        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h4 class="font-semibold text-slate-900">{{ $child->full_name }}</h4>
                                    <p class="mt-1 text-sm text-slate-500">{{ $enrollment?->classroom?->name ?? 'Aucune inscription active' }}</p>
                                </div>
                                @if ($summary)
                                    <span class="rounded-full bg-teal-50 px-3 py-1 text-sm font-semibold text-teal-800">{{ number_format($summary['average'], 2, ',', ' ') }} / 20</span>
                                @endif
                            </div>
                            <div class="mt-5 flex flex-wrap gap-3">
                                <a href="{{ route('guardian.attendance.index', ['student_id' => $child->id]) }}" class="rounded-lg bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-teal-50">Présences</a>
                                <a href="{{ route('guardian.grades.index') }}" class="rounded-lg bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-teal-50">Notes</a>
                                <a href="{{ route('guardian.payments.index', $schoolYear ? ['school_year_id' => $schoolYear->id] : []) }}" class="rounded-lg bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-teal-50">Finances</a>
                                <a href="{{ route('guardian.schedules.index') }}" class="rounded-lg bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-teal-50">Emploi du temps</a>
                            </div>
                            @if ($schoolYear && ($financialStatement = $financialStatements->get($child->id)))
                                <div class="mt-5 rounded-xl border border-slate-100 bg-slate-50 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Situation financière · {{ $schoolYear->label }}</p>
                                    @if ($financialStatement->error)
                                        <p class="mt-2 text-sm text-amber-800">{{ $financialStatement->error }}</p>
                                    @else
                                        <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                                            <div>
                                                <dt class="text-slate-500">Reste total</dt>
                                                <dd class="mt-1 font-semibold text-slate-900">{{ \App\Support\Format::fcfa($financialStatement->totalRemaining()) }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-slate-500">Impayés échus</dt>
                                                <dd class="mt-1 font-semibold {{ $financialStatement->arrears()->isNotEmpty() ? 'text-red-700' : 'text-slate-900' }}">{{ \App\Support\Format::fcfa($financialStatement->arrears()->sum('remaining')) }}</dd>
                                            </div>
                                        </dl>
                                    @endif
                                    <a href="{{ route('guardian.payments.index', ['school_year_id' => $schoolYear->id]) }}" class="mt-3 inline-flex text-sm font-semibold text-teal-800 hover:underline">Voir l’échéancier et les reçus</a>
                                </div>
                            @endif
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-10 text-sm text-slate-500 md:col-span-2 xl:col-span-3">
                            Aucun enfant n’est encore associé à ce compte. Contactez l’administration du centre pour mettre à jour votre dossier.
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-slate-900">Cours des enfants aujourd’hui</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ now()->locale(config('app.locale'))->translatedFormat('l d F') }}</p>
                    </div>
                    <a href="{{ route('guardian.schedules.index') }}" class="text-sm font-semibold text-teal-800 hover:underline">Voir la semaine</a>
                </div>
                <div class="mt-4 divide-y divide-slate-100">
                    @forelse ($todaySchedules as $schedule)
                        <div class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <div>
                                <p class="font-medium text-slate-900">{{ $schedule->assignment->subject->name }} · {{ $schedule->classroom->name }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $schedule->assignment->teacher->full_name }} · {{ $schedule->room->name }}</p>
                            </div>
                            <span class="text-sm font-semibold text-slate-700">{{ $schedule->startTime() }}–{{ $schedule->endTime() }}</span>
                        </div>
                    @empty
                        <p class="py-8 text-center text-sm text-slate-500">Aucun cours planifié aujourd’hui pour vos enfants.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
