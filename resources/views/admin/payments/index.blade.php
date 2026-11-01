<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Paiements</h2>
            <div class="flex gap-2">
                <a href="{{ route('admin.payments.arrears') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50">Impayés</a>
                <a href="{{ route('admin.payments.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">Nouveau paiement</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-flash />

            <form method="GET" class="bg-white p-4 shadow-sm sm:rounded-lg grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Élève, n° de reçu, référence…"
                       class="border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 lg:col-span-2">
                <x-select name="school_year_id" :options="$schoolYears" :selected="$yearId" placeholder="Toutes les années" />
                <input type="month" name="month" value="{{ request('month') }}" class="border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" aria-label="Mois concerné">
                <x-select name="method" :options="\App\Enums\PaymentMethod::options()" :selected="request('method')" placeholder="Tous les modes" />
                <x-select name="state" :options="['valid' => 'Valides', 'cancelled' => 'Annulés', 'all' => 'Tous']" :selected="$state" />
                <div class="flex gap-2 sm:col-span-3 lg:col-span-6">
                    <x-primary-button>Filtrer</x-primary-button>
                    <a href="{{ route('admin.payments.index') }}" class="inline-flex items-center px-3 text-sm text-gray-600 hover:underline">Réinitialiser</a>
                </div>
            </form>

            <div class="bg-white p-4 shadow-sm sm:rounded-lg text-sm">
                Total encaissé (paiements valides de cette sélection) :
                <strong class="text-lg text-gray-900">{{ \App\Support\Format::fcfa($total) }}</strong>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Reçu</th>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Élève</th>
                            <th class="px-4 py-3 hidden md:table-cell">Mois</th>
                            <th class="px-4 py-3 text-right">Montant</th>
                            <th class="px-4 py-3 hidden lg:table-cell">Mode</th>
                            <th class="px-4 py-3 hidden lg:table-cell">Enregistré par</th>
                            <th class="px-4 py-3"><span class="sr-only">Statut</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($payments as $payment)
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs whitespace-nowrap">
                                    <a href="{{ route('admin.payments.show', $payment) }}" class="text-indigo-700 hover:underline">{{ $payment->receipt_number }}</a>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ \App\Support\Format::date($payment->paid_at) }}</td>
                                <td class="px-4 py-3">
                                    {{ $payment->enrollment->student->full_name }}
                                    <span class="block text-xs text-gray-500">{{ $payment->enrollment->classroom->name }}</span>
                                </td>
                                <td class="px-4 py-3 hidden md:table-cell">{{ \App\Support\Format::month($payment->month) }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap font-medium">{{ \App\Support\Format::fcfa($payment->amount) }}</td>
                                <td class="px-4 py-3 hidden lg:table-cell">{{ $payment->method->label() }}</td>
                                <td class="px-4 py-3 hidden lg:table-cell">{{ $payment->recorder->name }}</td>
                                <td class="px-4 py-3">
                                    @if ($payment->isCancelled())<x-badge color="red">Annulé</x-badge>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">Aucun paiement trouvé.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $payments->links() }}
        </div>
    </div>
</x-app-layout>
