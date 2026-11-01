<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Élèves</h2>
            <a href="{{ route('admin.students.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                Nouvel élève
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-flash />

            <form method="GET" class="bg-white p-4 shadow-sm sm:rounded-lg grid gap-3 sm:grid-cols-6">
                <input type="search" name="search" value="{{ request('search') }}"
                       placeholder="Nom, prénom, matricule, téléphone…"
                       class="sm:col-span-2 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <x-select name="status" :options="\App\Enums\StudentStatus::options()" :selected="request('status')" placeholder="Tous les statuts" />
                <x-select name="gender" :options="\App\Enums\Gender::options()" :selected="request('gender')" placeholder="Tous les sexes" />
                <x-select name="archived" :options="['active' => 'Élèves actifs', 'archived' => 'Élèves archivés', 'all' => 'Tous les élèves']" :selected="request('archived', 'active')" />
                <div class="flex gap-2">
                    <x-primary-button>Filtrer</x-primary-button>
                    <a href="{{ route('admin.students.index') }}" class="inline-flex items-center px-3 text-sm text-gray-600 hover:underline">Réinitialiser</a>
                </div>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Matricule</th>
                            <th class="px-4 py-3">Élève</th>
                            <th class="px-4 py-3 hidden sm:table-cell">Naissance</th>
                            <th class="px-4 py-3 hidden md:table-cell">Tuteur</th>
                            <th class="px-4 py-3">Statut</th>
                            <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($students as $student)
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs">{{ $student->matricule }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.students.show', $student) }}" class="font-medium text-indigo-700 hover:underline">
                                        {{ $student->full_name }}
                                    </a>
                                    <span class="text-gray-500">({{ $student->gender->value }})</span>
                                    @if ($student->trashed())
                                        <x-badge color="gray">Archivé</x-badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3 hidden sm:table-cell">
                                    {{ \App\Support\Format::date($student->birth_date) }}
                                    <span class="text-gray-500">· {{ $student->age }} ans</span>
                                </td>
                                <td class="px-4 py-3 hidden md:table-cell">{{ $student->guardian?->full_name }}</td>
                                <td class="px-4 py-3">
                                    <x-badge :color="$student->status->color()">{{ $student->status->label() }}</x-badge>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    @unless ($student->trashed())
                                        <a href="{{ route('admin.students.edit', $student) }}" class="text-gray-600 hover:underline">Modifier</a>
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">Aucun élève trouvé.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $students->links() }}
        </div>
    </div>
</x-app-layout>
