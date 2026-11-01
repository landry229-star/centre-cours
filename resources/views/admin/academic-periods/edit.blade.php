<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wider text-teal-700">Configuration des périodes</p>
            <h2 class="mt-1 text-2xl font-bold text-slate-900">{{ $schoolYear->label }}</h2>
            <p class="mt-1 text-sm text-slate-500">
                Année scolaire du {{ \App\Support\Format::date($schoolYear->start_date) }} au {{ \App\Support\Format::date($schoolYear->end_date) }}.
            </p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-flash />

            @if ($isLocked)
                <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                    Cette année est verrouillée : des évaluations existent déjà. Ses périodes historiques ne peuvent plus être modifiées afin d’éviter le reclassement silencieux des notes.
                </div>
            @else
                <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                    Les périodes doivent se suivre sans chevauchement et couvrir exactement l’année scolaire. Elles seront verrouillées dès la création de la première évaluation.
                </div>
            @endif

            <form method="POST" action="{{ route('admin.academic-periods.update', $schoolYear) }}" class="space-y-6">
                @csrf
                @method('PUT')

                @foreach ([
                    'trimester' => ['title' => 'Trimestres', 'count' => 3],
                    'semester' => ['title' => 'Semestres', 'count' => 2],
                    'annual' => ['title' => 'Année complète', 'count' => 1],
                ] as $type => $definition)
                    <section class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-900">{{ $definition['title'] }}</h3>
                        <div class="grid gap-4 md:grid-cols-2">
                            @foreach (range(1, $definition['count']) as $number)
                                @php($period = collect($periods)->first(fn ($row) => $row['type'] === $type && $row['number'] === $number))
                                <fieldset class="space-y-3 rounded-xl border border-slate-200 p-4" @disabled($isLocked)>
                                    <legend class="px-1 text-sm font-semibold text-slate-700">
                                        {{ $type === 'annual' ? 'Bulletin annuel' : ucfirst(rtrim($definition['title'], 's')).' '.$number }}
                                    </legend>
                                    <label class="block space-y-1 text-sm text-slate-700">
                                        <span>Date de début</span>
                                        <input type="date" name="periods[{{ $type }}][{{ $number }}][start_date]" value="{{ old("periods.{$type}.{$number}.start_date", $period['start']->toDateString()) }}" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                                        @error("periods.{$type}.{$number}.start_date")<span class="text-xs text-red-700">{{ $message }}</span>@enderror
                                    </label>
                                    <label class="block space-y-1 text-sm text-slate-700">
                                        <span>Date de fin</span>
                                        <input type="date" name="periods[{{ $type }}][{{ $number }}][end_date]" value="{{ old("periods.{$type}.{$number}.end_date", $period['end']->toDateString()) }}" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                                        @error("periods.{$type}.{$number}.end_date")<span class="text-xs text-red-700">{{ $message }}</span>@enderror
                                    </label>
                                </fieldset>
                            @endforeach
                        </div>
                    </section>
                @endforeach

                @error('periods')<p class="text-sm text-red-700">{{ $message }}</p>@enderror

                @unless ($isLocked)
                    <div class="flex justify-end">
                        <x-primary-button>Enregistrer les dates</x-primary-button>
                    </div>
                @endunless
            </form>

            <a href="{{ route('admin.academic-periods.index') }}" class="inline-block text-sm font-semibold text-teal-800 hover:underline">← Toutes les années scolaires</a>
        </div>
    </div>
</x-app-layout>
