<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wider text-teal-700">Bulletins</p>
            <h2 class="mt-1 text-2xl font-bold text-slate-900">{{ $enrollment->student->full_name }}</h2>
            <p class="mt-1 text-sm text-slate-500">
                {{ $enrollment->classroom->name }} · {{ $enrollment->schoolYear->label }}
            </p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-4 px-4 sm:px-6 lg:px-8">
            <a href="{{ route('report-cards.index') }}" class="text-sm font-semibold text-teal-800 hover:underline">← Tous les bulletins</a>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($periods as $period)
                    <article class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div>
                            <h3 class="font-semibold text-slate-900">{{ $period['label'] }}</h3>
                            <p class="mt-1 text-sm text-slate-500">
                                Du {{ \App\Support\Format::date($period['start']) }} au {{ \App\Support\Format::date($period['end']) }}
                            </p>
                        </div>
                        <div class="mt-auto flex flex-wrap gap-3">
                            <a href="{{ route('report-cards.show', [
                                'enrollment' => $enrollment,
                                'period_type' => $period['type'],
                                'period_number' => $period['number'],
                            ]) }}" class="rounded-lg bg-teal-700 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-800">
                                Ouvrir le bulletin
                            </a>
                            <a href="{{ route('report-cards.show', [
                                'enrollment' => $enrollment,
                                'period_type' => $period['type'],
                                'period_number' => $period['number'],
                                'download' => 1,
                            ]) }}" class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200">
                                Télécharger
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
