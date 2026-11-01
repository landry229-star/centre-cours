<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nouveau paiement</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-flash />

            @if ($enrollment === null)
                {{-- Étape 1 : trouver l'élève (année active) --}}
                <form method="GET" class="bg-white p-6 shadow-sm sm:rounded-lg space-y-3">
                    <x-input-label for="search" value="Rechercher l'élève (nom ou matricule)" />
                    <div class="flex gap-2">
                        <input id="search" type="search" name="search" value="{{ $search }}" autofocus
                               class="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <x-primary-button>Chercher</x-primary-button>
                    </div>
                    <p class="text-xs text-gray-500">Seuls les élèves inscrits dans l'année scolaire active sont proposés.</p>
                </form>

                @if ($search)
                    <div class="bg-white shadow-sm sm:rounded-lg divide-y divide-gray-100">
                        @forelse ($candidates as $candidate)
                            <a href="{{ route('admin.payments.create', ['enrollment' => $candidate->id]) }}" class="flex items-center justify-between px-4 py-3 text-sm hover:bg-gray-50">
                                <span><strong>{{ $candidate->student->full_name }}</strong> <span class="font-mono text-xs text-gray-500">{{ $candidate->student->matricule }}</span></span>
                                <span class="text-gray-500">{{ $candidate->classroom->name }}</span>
                            </a>
                        @empty
                            <p class="px-4 py-6 text-center text-sm text-gray-500">Aucun élève inscrit ne correspond.</p>
                        @endforelse
                    </div>
                @endif
            @else
                @php
                    $payable = $statement?->rows->filter(fn ($r) => $r->remaining > 0)->values() ?? collect();
                    $remainingMap = $payable->mapWithKeys(fn ($r) => [$r->month->toDateString() => $r->remaining])->all();
                    $defaultMonth = old('month', $selectedMonth && isset($remainingMap[$selectedMonth]) ? $selectedMonth : ($payable->first(fn ($r) => $r->isArrear())?->month->toDateString() ?? $payable->first()?->month->toDateString()));
                @endphp

                <div class="bg-white p-4 shadow-sm sm:rounded-lg text-sm">
                    <p><strong>{{ $enrollment->student->full_name }}</strong> <span class="font-mono text-xs text-gray-500">{{ $enrollment->student->matricule }}</span></p>
                    <p class="text-gray-600">{{ $enrollment->classroom->name }} · {{ $enrollment->schoolYear->label }}
                        · <a href="{{ route('admin.enrollments.statement', $enrollment) }}" class="text-indigo-700 hover:underline">Voir l'échéancier</a></p>
                </div>

                @if ($statement === null || $statement->error)
                    <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                        {{ $statement?->error ?? 'Aucun échéancier pour cet élève.' }}
                        <a href="{{ route('admin.fee-schedules.index', ['school_year_id' => $enrollment->school_year_id]) }}" class="underline">Définir les tarifs</a>
                    </div>
                @elseif ($payable->isEmpty())
                    <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">Toutes les mensualités de {{ $enrollment->schoolYear->label }} sont soldées.</div>
                @else
                    <form method="POST" action="{{ route('admin.payments.store') }}" class="bg-white p-6 shadow-sm sm:rounded-lg space-y-4"
                          x-data="{ remaining: @js($remainingMap), month: @js($defaultMonth), amount: @js(old('amount', $remainingMap[$defaultMonth] ?? '')) }">
                        @csrf
                        <input type="hidden" name="enrollment_id" value="{{ $enrollment->id }}">

                        <div>
                            <x-input-label for="month" value="Mois concerné *" />
                            <select id="month" name="month" x-model="month" @change="amount = remaining[month] ?? ''" required
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                @foreach ($payable as $row)
                                    <option value="{{ $row->month->toDateString() }}">
                                        {{ \App\Support\Format::month($row->month) }} — reste {{ \App\Support\Format::fcfa($row->remaining) }}{{ $row->isArrear() ? ' (échu)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('month')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="amount" value="Montant versé (FCFA) *" />
                            <x-text-input id="amount" name="amount" type="text" inputmode="numeric" class="mt-1 block w-full" x-model="amount" required />
                            <p class="mt-1 text-xs text-gray-500">Pré-rempli avec le reste à payer. Un montant plus petit crée un paiement partiel.</p>
                            <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="method" value="Mode de paiement *" />
                                <x-select name="method" :options="$methods" :selected="old('method', 'cash')" class="mt-1" required />
                                <x-input-error :messages="$errors->get('method')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="paid_at" value="Date de paiement *" />
                                <x-text-input id="paid_at" name="paid_at" type="date" class="mt-1 block w-full" :value="old('paid_at', now()->toDateString())" max="{{ now()->toDateString() }}" required />
                                <x-input-error :messages="$errors->get('paid_at')" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="reference" value="Référence de transaction" />
                            <x-text-input id="reference" name="reference" type="text" class="mt-1 block w-full" :value="old('reference')" maxlength="100" />
                            <p class="mt-1 text-xs text-gray-500">Obligatoire pour Mobile Money et virement (n° de transaction). Inutile pour les espèces.</p>
                            <x-input-error :messages="$errors->get('reference')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="payer_name" value="Nom du payeur" />
                            <x-text-input id="payer_name" name="payer_name" type="text" class="mt-1 block w-full" :value="old('payer_name')" maxlength="150"
                                          placeholder="{{ $enrollment->student->guardian?->full_name ?? 'Parent / tuteur' }}" />
                            <p class="mt-1 text-xs text-gray-500">Laissez vide pour utiliser le parent / tuteur de l'élève.</p>
                            <x-input-error :messages="$errors->get('payer_name')" class="mt-2" />
                        </div>

                        <div class="flex items-center gap-3">
                            <x-primary-button>Enregistrer le paiement</x-primary-button>
                            <a href="{{ route('admin.payments.index') }}" class="text-sm text-gray-600 hover:underline">Annuler</a>
                        </div>
                    </form>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
