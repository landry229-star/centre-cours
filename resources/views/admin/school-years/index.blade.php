<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Années scolaires</h2>
            <a href="{{ route('admin.school-years.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                Nouvelle année
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-flash />

            <p class="text-sm text-gray-600">
                Une seule année est active à la fois. Activer une nouvelle année termine automatiquement les inscriptions en cours de l'année précédente.
            </p>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Année</th>
                            <th class="px-4 py-3 hidden sm:table-cell">Période</th>
                            <th class="px-4 py-3">Classes</th>
                            <th class="px-4 py-3 hidden md:table-cell">Inscriptions</th>
                            <th class="px-4 py-3">Statut</th>
                            <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($years as $year)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $year->label }}</td>
                                <td class="px-4 py-3 hidden sm:table-cell whitespace-nowrap">
                                    {{ \App\Support\Format::date($year->start_date) }} → {{ \App\Support\Format::date($year->end_date) }}
                                </td>
                                <td class="px-4 py-3">{{ $year->classrooms_count }}</td>
                                <td class="px-4 py-3 hidden md:table-cell">
                                    {{ $year->active_enrollments_count }} en cours / {{ $year->enrollments_count }} au total
                                </td>
                                <td class="px-4 py-3">
                                    @if ($year->is_active)
                                        <x-badge color="green">Active</x-badge>
                                    @else
                                        <x-badge>Inactive</x-badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap space-x-3">
                                    @unless ($year->is_active)
                                        <form method="POST" action="{{ route('admin.school-years.activate', $year) }}" class="inline"
                                              onsubmit="return confirm('Activer {{ $year->label }} ? Les inscriptions en cours de l\'année active seront terminées.');">
                                            @csrf
                                            <button type="submit" class="text-indigo-700 hover:underline">Activer</button>
                                        </form>
                                    @endunless
                                    <a href="{{ route('admin.school-years.edit', $year) }}" class="text-gray-600 hover:underline">Modifier</a>
                                    @unless ($year->is_active)
                                        <form method="POST" action="{{ route('admin.school-years.destroy', $year) }}" class="inline"
                                              onsubmit="return confirm('Supprimer cette année scolaire ?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:underline">Supprimer</button>
                                        </form>
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">Aucune année scolaire.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
