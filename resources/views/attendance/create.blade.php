<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nouvelle séance</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-flash />

            @if ($assignments->isEmpty())
                <div class="rounded-md bg-yellow-50 p-4 text-sm text-yellow-900">
                    Aucune matière à pointer : il faut une classe active (année scolaire en cours) avec un enseignant affecté à une matière.
                </div>
            @else
                <form method="POST" action="{{ route('attendance.store') }}" class="bg-white p-6 shadow-sm sm:rounded-lg space-y-4"
                      x-data="{ type: @js(old('session_type', 'lesson')), assignmentId: @js(old('assignment_id', '')) }">
                    @csrf

                    <div>
                        <x-input-label for="session_type" value="Type d’appel *" />
                        <select id="session_type" name="session_type" required x-model="type"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="daily">Appel quotidien de la classe</option>
                            <option value="lesson">Séance par matière</option>
                        </select>
                        <x-input-error :messages="$errors->get('session_type')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="lesson_assignment_id" value="Classe et matière *" x-show="type === 'lesson'" />
                        <x-input-label for="daily_assignment_id" value="Classe *" x-show="type === 'daily'" />
                        <select id="lesson_assignment_id" name="assignment_id" required
                                x-show="type === 'lesson'" x-bind:disabled="type !== 'lesson'" x-model="assignmentId"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">Choisir…</option>
                            @foreach ($assignments as $assignment)
                                <option value="{{ $assignment->id }}">
                                    {{ $assignment->classroom->name }} — {{ $assignment->subject->name }}@if (auth()->user()->isAdmin()) ({{ $assignment->teacher->full_name }})@endif
                                </option>
                            @endforeach
                        </select>
                        <select id="daily_assignment_id" name="assignment_id" required
                                x-show="type === 'daily'" x-bind:disabled="type !== 'daily'" x-model="assignmentId"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">Choisir…</option>
                            @foreach ($dailyAssignments as $assignment)
                                <option value="{{ $assignment->id }}">
                                    {{ $assignment->classroom->name }}@if (auth()->user()->isAdmin()) ({{ $assignment->teacher->full_name }})@endif
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('assignment_id')" class="mt-2" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3" x-show="type === 'lesson'">
                        <div>
                            <x-input-label for="date" value="Date *" />
                            <x-text-input id="date" name="date" type="date" class="mt-1 block w-full" :value="old('date', now()->toDateString())" max="{{ now()->toDateString() }}" required />
                            <x-input-error :messages="$errors->get('date')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="starts_at" value="Début *" />
                            <x-text-input id="starts_at" name="starts_at" type="time" x-bind:disabled="type === 'daily'" class="mt-1 block w-full" :value="old('starts_at', now()->format('H:i'))" required />
                            <x-input-error :messages="$errors->get('starts_at')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="ends_at" value="Fin" />
                            <x-text-input id="ends_at" name="ends_at" type="time" x-bind:disabled="type === 'daily'" class="mt-1 block w-full" :value="old('ends_at')" />
                            <x-input-error :messages="$errors->get('ends_at')" class="mt-2" />
                        </div>
                    </div>
                    <p class="text-xs text-gray-500" x-show="type === 'daily'">Un seul appel quotidien est possible par classe et par date.</p>
                    <p class="text-xs text-gray-500" x-show="type === 'lesson'">Si une séance existe déjà pour cette classe, cette matière, ce jour et cette heure, elle est rouverte : aucun doublon n'est créé.</p>

                    <div class="flex items-center gap-3">
                        <x-primary-button>Ouvrir la feuille d'appel</x-primary-button>
                        <a href="{{ route('attendance.index') }}" class="text-sm text-gray-600 hover:underline">Annuler</a>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
