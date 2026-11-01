<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $assignment->subject->name }} · {{ $assignment->classroom->name }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.assignments.update', $assignment) }}" class="bg-white p-6 shadow-sm sm:rounded-lg space-y-4">
                @csrf
                @method('PUT')

                {{-- La matière ne change pas : on garde la même, validée côté serveur par la règle d'unicité. --}}
                <input type="hidden" name="subject_id" value="{{ $assignment->subject_id }}">

                <div>
                    <x-input-label for="teacher_id" value="Enseignant *" />
                    <x-select name="teacher_id" class="mt-1" :options="$teachers" :selected="old('teacher_id', $assignment->teacher_id)" required />
                    <x-input-error :messages="$errors->get('teacher_id')" class="mt-2" />
                    <x-input-error :messages="$errors->get('subject_id')" class="mt-2" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="coefficient" value="Coefficient *" />
                        <x-text-input id="coefficient" name="coefficient" type="number" min="1" max="10" class="mt-1 block w-full" :value="old('coefficient', $assignment->coefficient)" required />
                        <x-input-error :messages="$errors->get('coefficient')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="weekly_hours" value="Heures / semaine" />
                        <x-text-input id="weekly_hours" name="weekly_hours" type="number" min="1" max="40" class="mt-1 block w-full" :value="old('weekly_hours', $assignment->weekly_hours)" />
                        <x-input-error :messages="$errors->get('weekly_hours')" class="mt-2" />
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <x-primary-button>Enregistrer</x-primary-button>
                    <a href="{{ route('admin.classrooms.show', $assignment->classroom_id) }}" class="text-sm text-gray-600 hover:underline">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
