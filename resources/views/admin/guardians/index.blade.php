<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Parents / Tuteurs</h2>
            <a href="{{ route('admin.guardians.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                Nouveau parent
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-flash />

            <form method="GET" class="bg-white p-4 shadow-sm sm:rounded-lg flex gap-3">
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Nom, téléphone, email…"
                       class="flex-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <x-primary-button>Rechercher</x-primary-button>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Parent</th>
                            <th class="px-4 py-3">Téléphone</th>
                            <th class="px-4 py-3 hidden md:table-cell">Email</th>
                            <th class="px-4 py-3">Enfants</th>
                            <th class="px-4 py-3 hidden sm:table-cell">Accès</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($guardians as $guardian)
                            <tr>
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.guardians.show', $guardian) }}" class="font-medium text-indigo-700 hover:underline">{{ $guardian->full_name }}</a>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $guardian->phone }}</td>
                                <td class="px-4 py-3 hidden md:table-cell">{{ $guardian->email ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $guardian->students_count }}</td>
                                <td class="px-4 py-3 hidden sm:table-cell">
                                    @if ($guardian->user_id)
                                        <x-badge color="green">Actif</x-badge>
                                    @else
                                        <x-badge>Aucun</x-badge>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Aucun parent trouvé.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $guardians->links() }}
        </div>
    </div>
</x-app-layout>
