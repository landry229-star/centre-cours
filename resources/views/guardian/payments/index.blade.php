<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider text-teal-700">Espace famille</p>
                <h2 class="mt-1 text-2xl font-bold text-slate-900">Situation financière</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Échéancier et paiements de vos enfants{{ $schoolYear ? ' · '.$schoolYear->label : '' }}
                </p>
            </div>
            <a href="{{ route('guardian.dashboard') }}" class="text-sm font-semibold text-teal-800 hover:underline">Retour au tableau de bord</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <form method="GET" class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end">
                <div class="w-full sm:max-w-xs">
                    <label for="school_year_id" class="block text-sm font-medium text-slate-700">Année scolaire</label>
                    <select id="school_year_id" name="school_year_id" class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-600 focus:ring-teal-600">
                        @foreach ($schoolYears as $year)
                            <option value="{{ $year->id }}" @selected($schoolYear?->id === $year->id)>{{ $year->label }}{{ $year->is_active ? ' · active' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <x-primary-button :disabled="$schoolYears->isEmpty()">Afficher</x-primary-button>
            </form>

            @if ($schoolYear)
                <section aria-label="Récapitulatif des échéanciers" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    @php($totalExpected = $statements->sum(fn ($statement) => $statement->totalExpected()))
                    @php($totalPaid = $statements->sum(fn ($statement) => $statement->totalPaid()))
                    @php($totalRemaining = $statements->sum(fn ($statement) => $statement->totalRemaining()))
                    @php($totalArrears = $statements->sum(fn ($statement) => $statement->arrears()->sum('remaining')))
                    <x-dashboard.stat-card label="Montant prévu" :value="\App\Support\Format::fcfa($totalExpected)" />
                    <x-dashboard.stat-card label="Montant payé" :value="\App\Support\Format::fcfa($totalPaid)" />
                    <x-dashboard.stat-card label="Reste total à payer" :value="\App\Support\Format::fcfa($totalRemaining)" />
                    <x-dashboard.stat-card label="Impayés exigibles" :value="\App\Support\Format::fcfa($totalArrears)" hint="Mensualités échues avec un solde restant." />
                </section>

                <section aria-labelledby="statements-heading" class="space-y-4">
                    <div>
                        <h3 id="statements-heading" class="text-lg font-semibold text-slate-900">Échéanciers par enfant</h3>
                        <p class="mt-1 text-sm text-slate-500">Le statut est calculé à partir des tarifs et des paiements valides.</p>
                    </div>

                    @forelse ($students as $student)
                        @php($statement = $statements->get($student->id))
                        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="flex flex-col gap-3 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h4 class="font-semibold text-slate-900">{{ $student->full_name }}</h4>
                                    <p class="mt-1 text-sm text-slate-500">
                                        {{ $student->enrollments->firstWhere('school_year_id', $schoolYear->id)?->classroom?->name ?? 'Aucune inscription pour cette année' }}
                                    </p>
                                </div>
                                @if ($statement)
                                    <div class="text-sm sm:text-right">
                                        <p class="text-slate-500">Reste à payer</p>
                                        <p class="font-semibold text-slate-900">{{ \App\Support\Format::fcfa($statement->totalRemaining()) }}</p>
                                    </div>
                                @endif
                            </div>

                            @if ($statement?->error)
                                <p class="p-5 text-sm text-amber-800">{{ $statement->error }}</p>
                            @elseif ($statement && $statement->rows->isNotEmpty())
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                            <tr>
                                                <th class="px-4 py-3">Mois</th>
                                                <th class="px-4 py-3 text-right">Attendu</th>
                                                <th class="px-4 py-3 text-right">Payé</th>
                                                <th class="px-4 py-3 text-right">Reste</th>
                                                <th class="px-4 py-3">Statut</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            @foreach ($statement->rows as $row)
                                                <tr>
                                                    <td class="whitespace-nowrap px-4 py-3">{{ \App\Support\Format::month($row->month) }}</td>
                                                    <td class="whitespace-nowrap px-4 py-3 text-right">{{ \App\Support\Format::fcfa($row->expected) }}</td>
                                                    <td class="whitespace-nowrap px-4 py-3 text-right">{{ \App\Support\Format::fcfa($row->paid) }}</td>
                                                    <td class="whitespace-nowrap px-4 py-3 text-right font-medium">{{ \App\Support\Format::fcfa($row->remaining) }}</td>
                                                    <td class="whitespace-nowrap px-4 py-3">
                                                        @if ($row->dueDate === null)
                                                            <x-badge color="gray">Date à définir</x-badge>
                                                        @elseif (! $row->due && $row->paid < $row->expected)
                                                            <x-badge color="gray">À venir</x-badge>
                                                        @else
                                                            <x-badge :color="$row->status->color()">{{ $row->status->label() }}</x-badge>
                                                        @endif
                                                        @if ($row->dueDate !== null)
                                                            <span class="block text-xs text-slate-500">Limite : {{ $row->dueDate->format('d/m/Y') }}</span>
                                                        @endif
                                                        @if ($row->isArrear())
                                                            <span class="ml-1 text-xs font-medium text-red-700">Échu</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="p-5 text-sm text-slate-500">Aucun échéancier pour cet enfant sur cette année scolaire.</p>
                            @endif
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center text-sm text-slate-500">
                            Aucun enfant n’est associé à ce compte.
                        </div>
                    @endforelse
                </section>
            @else
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center text-sm text-slate-500">
                    Aucun échéancier scolaire n’est disponible pour vos enfants.
                </div>
            @endif

            <section aria-labelledby="payments-heading" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 p-5">
                    <h3 id="payments-heading" class="font-semibold text-slate-900">Historique des paiements</h3>
                    <p class="mt-1 text-sm text-slate-500">Seuls les paiements valides réduisent le solde. Les paiements annulés restent visibles pour conserver l’historique.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Reçu</th>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Enfant</th>
                                <th class="px-4 py-3">Mois</th>
                                <th class="px-4 py-3 text-right">Montant</th>
                                <th class="px-4 py-3">Mode</th>
                                <th class="px-4 py-3">État</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($payments as $payment)
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-3 font-mono text-xs">
                                        <a href="{{ route('payments.receipt', $payment) }}" class="font-semibold text-teal-800 hover:underline">{{ $payment->receipt_number }}</a>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ \App\Support\Format::date($payment->paid_at) }}</td>
                                    <td class="px-4 py-3">{{ $payment->enrollment->student->full_name }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ \App\Support\Format::month($payment->month) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-medium">{{ \App\Support\Format::fcfa($payment->amount) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ $payment->method->label() }}</td>
                                    <td class="px-4 py-3">
                                        @if ($payment->isCancelled())
                                            <x-badge color="red">Annulé</x-badge>
                                        @else
                                            <x-badge color="green">Enregistré</x-badge>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-slate-500">Aucun paiement enregistré pour cette année.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-4">{{ $payments->links() }}</div>
            </section>
        </div>
    </div>
</x-app-layout>
