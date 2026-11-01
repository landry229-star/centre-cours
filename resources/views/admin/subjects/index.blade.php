<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Matières</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-flash />

            <form method="POST" action="{{ route('admin.subjects.store') }}" class="bg-white p-4 shadow-sm sm:rounded-lg grid gap-3 sm:grid-cols-3 items-start">
                @csrf
                <div class="sm:col-span-1">
                    <x-text-input name="name" type="text" class="block w-full" :value="old('name')" placeholder="Nom (ex. Mathématiques)" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
                <div>
                    <x-text-input name="code" type="text" class="block w-full uppercase" :value="old('code')" placeholder="Code (ex. MATH)" maxlength="10" required />
                    <x-input-error :messages="$errors->get('code')" class="mt-1" />
                </div>
                <div><x-primary-button>Ajouter la matière</x-primary-button></div>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Code</th>
                            <th class="px-4 py-3">Matière</th>
                            <th class="px-4 py-3">Classes</th>
                            <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($subjects as $subject)
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs">{{ $subject->code }}</td>
                                <td class="px-4 py-3 font-medium">{{ $subject->name }}</td>
                                <td class="px-4 py-3">{{ $subject->assignments_count }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap space-x-3">
                                    <a href="{{ route('admin.subjects.edit', $subject) }}" class="text-gray-600 hover:underline">Modifier</a>
                                    <form method="POST" action="{{ route('admin.subjects.destroy', $subject) }}" class="inline"
                                          onsubmit="return confirm('Supprimer cette matière ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:underline">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">Aucune matière. Ajoutez la première ci-dessus.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
