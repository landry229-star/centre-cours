<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wider text-teal-700">Administration</p>
            <h2 class="mt-1 text-2xl font-bold text-slate-900">Bonjour, {{ auth()->user()->name }}</h2>
            <p class="mt-1 text-sm text-slate-500">
                {{ $schoolYear ? 'Année scolaire '.$schoolYear->label : 'Aucune année scolaire active' }}
            </p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
            <section aria-label="Indicateurs de l’établissement" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-dashboard.stat-card label="Élèves actifs" :value="$metrics['students']" />
                <x-dashboard.stat-card label="Enseignants actifs" :value="$metrics['teachers']" />
                <x-dashboard.stat-card label="Classes ouvertes" :value="$metrics['classrooms']" :hint="$schoolYear?->label" />
                <x-dashboard.stat-card label="Inscriptions en cours" :value="$metrics['enrollments']" />
                <x-dashboard.stat-card label="Absences signalées aujourd’hui" :value="$metrics['absences']" />
                <x-dashboard.stat-card label="Encaissé ce mois" :value="\App\Support\Format::fcfa($metrics['collected'])" />
                <x-dashboard.stat-card label="Paiements ce mois" :value="$metrics['payments_this_month']" />
                <x-dashboard.stat-card
                    label="Impayés à date"
                    :value="\App\Support\Format::fcfa($metrics['arrears_amount'])"
                    :hint="$metrics['arrears_students'].' élève(s) concerné(s)'"
                />
            </section>

            <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1.2fr_0.8fr]">
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4">
                        <div>
                            <h3 class="font-semibold text-slate-900">Derniers paiements</h3>
                            <p class="mt-1 text-sm text-slate-500">Paiements valides de l’année scolaire active</p>
                        </div>
                        <a href="{{ route('admin.payments.index') }}" class="text-sm font-semibold text-teal-800 hover:underline">Tous les paiements</a>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @forelse ($recentPayments as $payment)
                            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                                <div>
                                    <p class="font-medium text-slate-900">{{ $payment->enrollment->student->full_name }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ $payment->receipt_number }} · {{ \App\Support\Format::date($payment->paid_at) }}</p>
                                </div>
                                <span class="font-semibold text-slate-900">{{ \App\Support\Format::fcfa($payment->amount) }}</span>
                            </div>
                        @empty
                            <p class="px-5 py-8 text-center text-sm text-slate-500">Aucun paiement à afficher pour cette année.</p>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="font-semibold text-slate-900">Accès rapides</h3>
                    <p class="mt-1 text-sm text-slate-500">Les tâches courantes de l’administration</p>
                    <div class="mt-5 grid gap-3">
                        <a href="{{ route('admin.students.create') }}" class="rounded-xl bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-800 transition hover:bg-teal-50 hover:text-teal-900">Ajouter un élève <span aria-hidden="true">→</span></a>
                        <a href="{{ route('admin.enrollments.index') }}" class="rounded-xl bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-800 transition hover:bg-teal-50 hover:text-teal-900">Gérer les inscriptions <span aria-hidden="true">→</span></a>
                        <a href="{{ route('admin.reports.index') }}" class="rounded-xl bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-800 transition hover:bg-teal-50 hover:text-teal-900">Consulter le pilotage <span aria-hidden="true">→</span></a>
                        <a href="{{ route('admin.payments.arrears') }}" class="rounded-xl bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-800 transition hover:bg-teal-50 hover:text-teal-900">Consulter les impayés <span aria-hidden="true">→</span></a>
                        <a href="{{ route('admin.schedules.index') }}" class="rounded-xl bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-800 transition hover:bg-teal-50 hover:text-teal-900">Voir les emplois du temps <span aria-hidden="true">→</span></a>
                        <a href="{{ route('admin.communications.announcements.create') }}" class="rounded-xl bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-800 transition hover:bg-teal-50 hover:text-teal-900">Publier une annonce <span aria-hidden="true">→</span></a>
                        <a href="{{ route('communications.messages.index') }}" class="rounded-xl bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-800 transition hover:bg-teal-50 hover:text-teal-900">Messages des familles <span aria-hidden="true">→</span></a>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h3 class="font-semibold text-slate-900">Évolution mensuelle</h3>
                        <p class="mt-1 text-sm text-slate-500">Inscriptions et encaissements des six derniers mois</p>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @foreach ($monthlyTrends as $trend)
                            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                                <p class="font-medium capitalize text-slate-900">{{ $trend['label'] }}</p>
                                <div class="flex flex-wrap gap-x-5 gap-y-1 text-sm text-slate-600">
                                    <span>{{ $trend['enrollment_count'] }} inscription(s)</span>
                                    <span>{{ $trend['payment_count'] }} paiement(s)</span>
                                    <span class="font-semibold text-slate-900">{{ \App\Support\Format::fcfa($trend['payment_amount']) }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h3 class="font-semibold text-slate-900">Paiements par moyen</h3>
                        <p class="mt-1 text-sm text-slate-500">Répartition des paiements valides de l’année active</p>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @foreach ($paymentMethods as $paymentMethod)
                            <div class="flex items-center justify-between gap-4 px-5 py-4">
                                <div>
                                    <p class="font-medium text-slate-900">{{ $paymentMethod['method']->label() }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ $paymentMethod['count'] }} paiement(s)</p>
                                </div>
                                <span class="font-semibold text-slate-900">{{ \App\Support\Format::fcfa($paymentMethod['amount']) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
