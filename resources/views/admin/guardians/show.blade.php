<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $guardian->full_name }}</h2>
            <a href="{{ route('admin.guardians.edit', $guardian) }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                Modifier
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-flash />

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Coordonnées</h3>
                <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500">Téléphone</dt><dd>{{ $guardian->phone }}</dd></div>
                    <div><dt class="text-gray-500">Email</dt><dd>{{ $guardian->email ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Profession</dt><dd>{{ $guardian->profession ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Adresse</dt><dd>{{ $guardian->address ?? '—' }}</dd></div>
                </dl>
            </div>

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Accès à l'espace parent</h3>
                <div class="mt-3 text-sm">
                    @if ($guardian->user)
                        <x-badge :color="$guardian->user->is_active ? 'green' : 'red'">
                            {{ $guardian->user->is_active ? 'Accès actif' : 'Accès désactivé' }}
                        </x-badge>
                        <span class="ml-2 text-gray-600">Identifiant : {{ $guardian->user->email }}</span>
                    @elseif ($guardian->email)
                        <p class="text-gray-600">Ce parent n'a pas encore d'accès.</p>
                        <form method="POST" action="{{ route('admin.guardians.account.store', $guardian) }}" class="mt-3">
                            @csrf
                            <x-secondary-button type="submit">Créer l'accès</x-secondary-button>
                        </form>
                    @else
                        <p class="text-gray-600">Ajoutez une adresse email pour pouvoir créer un accès.</p>
                    @endif
                </div>
            </div>

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Enfants ({{ $guardian->students->count() }})</h3>
                    <a href="{{ route('admin.students.create', ['guardian_id' => $guardian->id]) }}" class="text-sm text-indigo-700 hover:underline">Ajouter un élève</a>
                </div>
                <ul class="mt-3 divide-y divide-gray-100 text-sm">
                    @forelse ($guardian->students as $student)
                        <li class="flex items-center justify-between py-2">
                            <a href="{{ route('admin.students.show', $student) }}" class="text-indigo-700 hover:underline">{{ $student->full_name }}</a>
                            <span class="font-mono text-xs text-gray-500">{{ $student->matricule }}</span>
                        </li>
                    @empty
                        <li class="py-2 text-gray-500">Aucun élève rattaché.</li>
                    @endforelse
                </ul>
            </div>

            <form method="POST" action="{{ route('admin.guardians.destroy', $guardian) }}"
                  onsubmit="return confirm('Archiver ce parent ? Son accès sera désactivé.');">
                @csrf
                @method('DELETE')
                <x-danger-button>Archiver ce parent</x-danger-button>
            </form>
        </div>
    </div>
</x-app-layout>
