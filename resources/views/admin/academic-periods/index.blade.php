<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wider text-teal-700">Administration</p>
            <h2 class="mt-1 text-2xl font-bold text-slate-900">Périodes scolaires</h2>
            <p class="mt-1 text-sm text-slate-500">Configurez les dates des trimestres, semestres et bulletins annuels.</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-4 px-4 sm:px-6 lg:px-8">
            <x-flash />

            <section class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Année scolaire</th>
                            <th class="px-4 py-3">Dates</th>
                            <th class="px-4 py-3 text-right">Configuration</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($schoolYears as $schoolYear)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-slate-900">{{ $schoolYear->label }}</td>
                                <td class="px-4 py-3 text-slate-600">
                                    {{ \App\Support\Format::date($schoolYear->start_date) }} – {{ \App\Support\Format::date($schoolYear->end_date) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.academic-periods.edit', $schoolYear) }}" class="font-semibold text-teal-800 hover:underline">
                                        Configurer les périodes
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>

            {{ $schoolYears->links() }}
        </div>
    </div>
</x-app-layout>
