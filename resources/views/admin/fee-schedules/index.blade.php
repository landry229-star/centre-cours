<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Tarifs mensuels</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-flash />

            <form method="GET" class="bg-white p-4 shadow-sm sm:rounded-lg flex flex-wrap items-center gap-3">
                <label for="school_year_id" class="text-sm text-gray-600">Année scolaire</label>
                <div class="w-48"><x-select name="school_year_id" :options="$schoolYears" :selected="$year?->id" /></div>
                <x-primary-button>Afficher</x-primary-button>
            </form>

            @if ($year === null)
                <p class="bg-white p-6 shadow-sm sm:rounded-lg text-gray-500">Créez d'abord une année scolaire.</p>
            @else
                <p class="text-sm text-gray-600">
                    Montant demandé <strong>chaque mois</strong> pour un élève de chaque niveau, en FCFA, pour {{ $year->label }}.
                    Un tarif devient <strong>verrouillé</strong> dès qu'un paiement a été enregistré pour ce niveau.
                </p>

                <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Niveau</th>
                                <th class="px-4 py-3">Tarif mensuel (FCFA)</th>
                                <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($levels as $level)
                                @php($fee = $fees->get($level->id))
                                @php($isLocked = $fee && ($locked[$level->id] ?? false))
                                <tr>
                                    <td class="px-4 py-3 font-medium">{{ $level->name }}</td>
                                    <td class="px-4 py-3">
                                        <form method="POST" action="{{ route('admin.fee-schedules.store') }}" class="flex items-center gap-2">
                                            @csrf
                                            <input type="hidden" name="school_year_id" value="{{ $year->id }}">
                                            <input type="hidden" name="level_id" value="{{ $level->id }}">
                                            <x-text-input name="monthly_amount" type="text" inputmode="numeric" class="block w-32"
                                                          :value="$fee?->monthly_amount" placeholder="ex. 15000" :disabled="$isLocked" required />
                                            @unless ($isLocked)
                                                <x-primary-button>{{ $fee ? 'Modifier' : 'Définir' }}</x-primary-button>
                                            @endunless
                                        </form>
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        @if ($isLocked)
                                            <x-badge color="yellow">Verrouillé</x-badge>
                                        @elseif ($fee)
                                            <form method="POST" action="{{ route('admin.fee-schedules.destroy', $fee) }}" class="inline"
                                                  onsubmit="return confirm('Supprimer ce tarif ?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:underline">Supprimer</button>
                                            </form>
                                        @else
                                            <x-badge color="red">Non défini</x-badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <x-input-error :messages="$errors->get('monthly_amount')" />
            @endif
        </div>
    </div>
</x-app-layout>
