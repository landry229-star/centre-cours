<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Échéancier : {{ $enrollment->student->full_name }}</h2>
            <a href="{{ route('admin.payments.create', ['enrollment' => $enrollment->id]) }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">Encaisser</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-flash />

            <div class="bg-white p-4 shadow-sm sm:rounded-lg text-sm">
                <a href="{{ route('admin.students.show', $enrollment->student) }}" class="font-medium text-indigo-700 hover:underline">{{ $enrollment->student->full_name }}</a>
                <span class="font-mono text-xs text-gray-500">{{ $enrollment->student->matricule }}</span>
                · {{ $enrollment->classroom->name }} · {{ $enrollment->schoolYear->label }}
            </div>

            @if ($statement === null || $statement->error)
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    {{ $statement?->error ?? 'Aucun échéancier pour cet élève.' }}
                    <a href="{{ route('admin.fee-schedules.index', ['school_year_id' => $enrollment->school_year_id]) }}" class="underline">Définir les tarifs</a>
                </div>
            @else
                <div class="grid gap-3 sm:grid-cols-3 text-sm">
                    <div class="bg-white p-4 shadow-sm sm:rounded-lg"><p class="text-gray-500">Total attendu</p><p class="text-lg font-semibold">{{ \App\Support\Format::fcfa($statement->totalExpected()) }}</p></div>
                    <div class="bg-white p-4 shadow-sm sm:rounded-lg"><p class="text-gray-500">Total payé</p><p class="text-lg font-semibold text-green-700">{{ \App\Support\Format::fcfa($statement->totalPaid()) }}</p></div>
                    <div class="bg-white p-4 shadow-sm sm:rounded-lg"><p class="text-gray-500">Reste à payer</p><p class="text-lg font-semibold text-red-700">{{ \App\Support\Format::fcfa($statement->totalRemaining()) }}</p></div>
                </div>

                <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Mois</th>
                                <th class="px-4 py-3">Date limite</th>
                                <th class="px-4 py-3 text-right">Montant attendu</th>
                                <th class="px-4 py-3 text-right">Montant payé</th>
                                <th class="px-4 py-3 text-right">Reste</th>
                                <th class="px-4 py-3">Statut</th>
                                <th class="px-4 py-3"><span class="sr-only">Action</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($statement->rows as $row)
                                <tr>
                                    <td class="px-4 py-3 capitalize">{{ \App\Support\Format::month($row->month) }}</td>
                                    <td class="px-4 py-3">
                                        <form method="POST" action="{{ route('admin.enrollments.due-date', $enrollment) }}" class="flex items-center gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="month" value="{{ $row->month->toDateString() }}">
                                            <input type="date" name="due_date" value="{{ $row->dueDate?->toDateString() }}"
                                                   aria-label="Date limite pour {{ \App\Support\Format::month($row->month) }}"
                                                   class="w-36 rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <button type="submit" class="text-xs font-medium text-indigo-700 hover:underline">Enregistrer</button>
                                        </form>
                                        @if ($row->dueDate === null)
                                            <span class="text-xs text-amber-700">À définir</span>
                                        @elseif ($row->isArrear())
                                            <span class="text-xs text-red-700">Échu depuis le {{ $row->dueDate->copy()->addDay()->format('d/m/Y') }}</span>
                                        @endif
                                        @php($schedule = $dueDates->get($row->month->toDateString()))
                                        @if ($schedule?->changes->isNotEmpty())
                                            <details class="mt-1 text-xs text-gray-500">
                                                <summary class="cursor-pointer hover:underline">Historique ({{ $schedule->changes->count() }})</summary>
                                                <ul class="mt-1 space-y-1">
                                                    @foreach ($schedule->changes as $change)
                                                        <li>
                                                            {{ $change->created_at->format('d/m/Y H:i') }} ·
                                                            {{ $change->old_due_date?->format('d/m/Y') ?? 'aucune date' }}
                                                            → {{ $change->new_due_date?->format('d/m/Y') ?? 'date effacée' }}
                                                            · {{ $change->changedBy->name }}
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </details>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">{{ \App\Support\Format::fcfa($row->expected) }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">{{ \App\Support\Format::fcfa($row->paid) }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">{{ \App\Support\Format::fcfa($row->remaining) }}</td>
                                    <td class="px-4 py-3">
                                        <x-badge :color="$row->status->color()">{{ $row->status->label() }}</x-badge>
                                        @if ($row->isArrear())<span class="ml-1 text-xs text-red-700">échu</span>@endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if ($row->remaining > 0)
                                            <a href="{{ route('admin.payments.create', ['enrollment' => $enrollment->id, 'month' => $row->month->toDateString()]) }}" class="text-indigo-700 hover:underline">Encaisser</a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">Aucune mensualité à afficher.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <h3 class="px-4 pt-4 text-sm font-semibold uppercase tracking-wider text-gray-500">Paiements enregistrés</h3>
                <table class="min-w-full divide-y divide-gray-200 text-sm mt-2">
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($payments as $payment)
                            <tr class="{{ $payment->isCancelled() ? 'text-gray-400 line-through' : '' }}">
                                <td class="px-4 py-2 font-mono text-xs"><a href="{{ route('admin.payments.show', $payment) }}" class="text-indigo-700 hover:underline">{{ $payment->receipt_number }}</a></td>
                                <td class="px-4 py-2">{{ \App\Support\Format::date($payment->paid_at) }}</td>
                                <td class="px-4 py-2 capitalize">{{ \App\Support\Format::month($payment->month) }}</td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">{{ \App\Support\Format::fcfa($payment->amount) }}</td>
                                <td class="px-4 py-2">{{ $payment->method->label() }}</td>
                            </tr>
                        @empty
                            <tr><td class="px-4 py-6 text-center text-gray-500">Aucun paiement pour le moment.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
