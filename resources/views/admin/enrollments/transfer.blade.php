<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Changer de classe : {{ $enrollment->student->full_name }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.enrollments.transfer.store', $enrollment) }}" class="bg-white p-6 shadow-sm sm:rounded-lg space-y-4">
                @csrf

                <p class="text-sm text-gray-600">
                    Classe actuelle : <strong>{{ $enrollment->classroom->name }}</strong> ({{ $enrollment->schoolYear->label }}).
                    L'inscription actuelle sera conservée dans l'historique.
                </p>

                <div>
                    <x-input-label for="classroom_id" value="Nouvelle classe *" />
                    <select id="classroom_id" name="classroom_id" required
                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="">Choisir une classe…</option>
                        @foreach ($classrooms as $classroom)
                            @php $full = $classroom->active_enrollments_count >= $classroom->capacity; @endphp
                            <option value="{{ $classroom->id }}" @disabled($full) @selected((string) old('classroom_id') === (string) $classroom->id)>
                                {{ $classroom->name }} ({{ $classroom->level->name }}) · {{ $classroom->active_enrollments_count }}/{{ $classroom->capacity }} places @if ($full) — complète @endif
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('classroom_id')" class="mt-2" />
                    @if ($classrooms->isEmpty())
                        <p class="mt-2 text-sm text-gray-500">Aucune autre classe ouverte pour cette année scolaire.</p>
                    @endif
                </div>

                <div>
                    <x-input-label for="date" value="Date du changement *" />
                    <x-text-input id="date" name="date" type="date" class="mt-1 block w-full" :value="old('date', now()->toDateString())" max="{{ now()->toDateString() }}" required />
                    <x-input-error :messages="$errors->get('date')" class="mt-2" />
                </div>

                <div class="flex items-center gap-3">
                    <x-primary-button>Confirmer le changement</x-primary-button>
                    <a href="{{ route('admin.students.show', $enrollment->student_id) }}" class="text-sm text-gray-600 hover:underline">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
