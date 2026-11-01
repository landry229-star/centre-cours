<form method="POST" action="{{ $schedule ? route('admin.schedules.update', $schedule) : route('admin.schedules.store') }}" class="space-y-5 rounded-lg bg-white p-6 shadow-sm">
    @csrf
    @if ($schedule) @method('PUT') @endif

    <div>
        <x-input-label for="assignment_id" value="Classe, matière et enseignant" />
        <x-select id="assignment_id" name="assignment_id" :options="$assignments->all()" :selected="old('assignment_id', $schedule?->teaching_assignment_id)" placeholder="Choisir une affectation" class="mt-1 block w-full" />
        <x-input-error :messages="$errors->get('assignment_id')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="room_id" value="Salle" />
        <x-select id="room_id" name="room_id" :options="$rooms" :selected="old('room_id', $schedule?->room_id)" placeholder="Choisir une salle" class="mt-1 block w-full" />
        <x-input-error :messages="$errors->get('room_id')" class="mt-2" />
    </div>

    <div class="grid gap-5 sm:grid-cols-3">
        <div>
            <x-input-label for="day_of_week" value="Jour" />
            <x-select id="day_of_week" name="day_of_week" :options="$days" :selected="old('day_of_week', $schedule?->day_of_week?->value)" placeholder="Choisir un jour" class="mt-1 block w-full" />
            <x-input-error :messages="$errors->get('day_of_week')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="starts_at" value="Début" />
            <x-text-input id="starts_at" name="starts_at" type="time" class="mt-1 block w-full" :value="old('starts_at', $schedule?->startTime())" required />
            <x-input-error :messages="$errors->get('starts_at')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="ends_at" value="Fin" />
            <x-text-input id="ends_at" name="ends_at" type="time" class="mt-1 block w-full" :value="old('ends_at', $schedule?->endTime())" required />
            <x-input-error :messages="$errors->get('ends_at')" class="mt-2" />
        </div>
    </div>

    <p class="text-sm text-gray-600">Les conflits de classe, d’enseignant et de salle sont refusés. Les séances sont hebdomadaires pour l’année scolaire active.</p>
    <div class="flex flex-wrap gap-3">
        <x-primary-button>{{ $schedule ? 'Enregistrer les changements' : 'Ajouter la séance' }}</x-primary-button>
        <a href="{{ route('admin.schedules.index') }}" class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Annuler</a>
    </div>
</form>
