<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Enseignants</h2>
            <a href="{{ route('admin.teachers.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                Nouvel enseignant
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-flash />

            <form method="GET" class="bg-white p-4 shadow-sm sm:rounded-lg grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                <input type="search" name="search" value="{{ request('search') }}"
                       placeholder="Nom, matricule, spécialité, téléphone…"
                       class="border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:col-span-2">
                <x-select name="specialty" :options="$specialties" :selected="request('specialty')" placeholder="Toutes les matières" />
                <x-select name="status" :options="\App\Enums\TeacherStatus::options()" :selected="request('status')" placeholder="Tous les statuts" />
                <x-select name="access" :options="['none' => 'Sans accès', 'active' => 'Accès actif', 'disabled' => 'Accès désactivé']" :selected="request('access')" placeholder="Tous les accès" />
                <x-select name="assignments" :options="['none' => 'Sans affectation', 'assigned' => 'Avec affectation']" :selected="request('assignments')" placeholder="Toutes les affectations" />
                <div class="flex gap-2">
                    <x-primary-button>Filtrer</x-primary-button>
                    <a href="{{ route('admin.teachers.index') }}" class="inline-flex items-center px-3 text-sm text-gray-600 hover:underline">Réinitialiser</a>
                </div>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Matricule</th>
                            <th class="px-4 py-3">Enseignant</th>
                            <th class="px-4 py-3 hidden sm:table-cell">Spécialité</th>
                            <th class="px-4 py-3 hidden md:table-cell">Téléphone</th>
                            <th class="px-4 py-3">Statut</th>
                            <th class="px-4 py-3">Accès</th>
                            <th class="px-4 py-3">Affectations</th>
                            <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($teachers as $teacher)
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs">{{ $teacher->matricule }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.teachers.show', $teacher) }}" class="font-medium text-indigo-700 hover:underline">{{ $teacher->full_name }}</a>
                                </td>
                                <td class="px-4 py-3 hidden sm:table-cell">{{ $teacher->specialty }}</td>
                                <td class="px-4 py-3 hidden md:table-cell whitespace-nowrap">{{ $teacher->phone }}</td>
                                <td class="px-4 py-3"><x-badge :color="$teacher->status->color()">{{ $teacher->status->label() }}</x-badge></td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if ($teacher->user === null)
                                        <x-badge color="yellow">Sans accès</x-badge>
                                    @elseif (! $teacher->user->is_active)
                                        <x-badge color="red">Désactivé</x-badge>
                                    @elseif ($teacher->user->must_change_password)
                                        <x-badge color="yellow">À configurer</x-badge>
                                    @else
                                        <x-badge color="green">Actif</x-badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if ($teacher->assignments_count === 0)
                                        <x-badge color="yellow">Aucune</x-badge>
                                    @else
                                        {{ $teacher->assignments_count }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('admin.teachers.edit', $teacher) }}" class="text-gray-600 hover:underline">Modifier</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">Aucun enseignant trouvé.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $teachers->links() }}
        </div>
    </div>
</x-app-layout>
