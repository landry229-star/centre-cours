<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Impayés {{ $year ? '· '.$year->label : '' }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-flash />

            <form method="GET" class="bg-white p-4 shadow-sm sm:rounded-lg grid gap-3 sm:grid-cols-4">
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Nom ou matricule…"
                       class="border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:col-span-2">
                <x-select name="school_year_id" :options="$schoolYears" :selected="$year?->id" />
                <div class="flex gap-2">
                    <x-primary-button>Filtrer</x-primary-button>
                    <a href="{{ route('admin.payments.arrears') }}" class="inline-flex items-center px-3 text-sm text-gray-600 hover:underline">Réinitialiser</a>
                </div>
            </form>

            @if ($withoutFee->isNotEmpty())
                <div class="rounded-md bg-yellow-50 p-4 text-sm text-yellow-900">
                    {{ $withoutFee->count() }} élève(s) ne peuvent pas être évalués faute de tarif :
                    <a href="{{ route('admin.fee-schedules.index', ['school_year_id' => $year?->id]) }}" class="underline">définir les tarifs manquants</a>.
                </div>
            @endif

            <div class="grid gap-3 sm:grid-cols-2 text-sm">
                <div class="bg-white p-4 shadow-sm sm:rounded-lg"><p class="text-gray-500">Total des impayés échus</p><p class="text-lg font-semibold text-red-700">{{ \App\Support\Format::fcfa($totalRemaining) }}</p></div>
                <div class="bg-white p-4 shadow-sm sm:rounded-lg"><p class="text-gray-500">Élèves concernés</p><p class="text-lg font-semibold">{{ $studentsCount }}</p></div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Élève</th>
                            <th class="px-4 py-3">Mois</th>
                            <th class="px-4 py-3 text-right">Montant attendu</th>
                            <th class="px-4 py-3 text-right">Montant payé</th>
                            <th class="px-4 py-3 text-right">Reste</th>
                            <th class="px-4 py-3">Statut</th>
                            <th class="px-4 py-3"><span class="sr-only">Action</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($rows as $row)
                            <tr>
                                <td class="px-4 py-3">
                                    {{ $row['student']->full_name }}
                                    <span class="block font-mono text-xs text-gray-500">{{ $row['student']->matricule }}</span>
                                </td>
                                <td class="px-4 py-3 capitalize">{{ \App\Support\Format::month($row['fee']->month) }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">{{ \App\Support\Format::fcfa($row['fee']->expected) }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">{{ \App\Support\Format::fcfa($row['fee']->paid) }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap font-medium">{{ \App\Support\Format::fcfa($row['fee']->remaining) }}</td>
                                <td class="px-4 py-3"><x-badge :color="$row['fee']->status->color()">{{ $row['fee']->status->label() }}</x-badge></td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.payments.create', ['enrollment' => $row['fee']->enrollmentId, 'month' => $row['fee']->month->toDateString()]) }}" class="text-indigo-700 hover:underline">Encaisser</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">Aucun impayé échu. 🎉</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $rows->links() }}
        </div>
    </div>
</x-app-layout>
