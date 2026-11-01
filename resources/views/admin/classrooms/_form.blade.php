@php $classroom = $classroom ?? null; @endphp

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="school_year_id" value="Année scolaire *" />
            <x-select name="school_year_id" class="mt-1" :options="$schoolYears" :selected="old('school_year_id', $classroom?->school_year_id ?? $activeYearId)" placeholder="Choisir…" required />
            <x-input-error :messages="$errors->get('school_year_id')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="level_id" value="Niveau *" />
            <x-select name="level_id" class="mt-1" :options="$levels" :selected="old('level_id', $classroom?->level_id)" placeholder="Choisir…" required />
            <x-input-error :messages="$errors->get('level_id')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="name" value="Nom de la classe *" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $classroom?->name)" placeholder="6ème A" required />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="capacity" value="Capacité *" />
            <x-text-input id="capacity" name="capacity" type="number" min="1" max="200" class="mt-1 block w-full" :value="old('capacity', $classroom?->capacity ?? 40)" required />
            <x-input-error :messages="$errors->get('capacity')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="room_id" value="Salle" />
            <x-select name="room_id" class="mt-1" :options="$rooms" :selected="old('room_id', $classroom?->room_id)" placeholder="Aucune" />
            <x-input-error :messages="$errors->get('room_id')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="head_teacher_id" value="Professeur principal" />
            <x-select name="head_teacher_id" class="mt-1" :options="$teachers" :selected="old('head_teacher_id', $classroom?->head_teacher_id)" placeholder="Aucun" />
            <x-input-error :messages="$errors->get('head_teacher_id')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="status" value="Statut *" />
            <x-select name="status" class="mt-1" :options="\App\Enums\ClassroomStatus::options()" :selected="old('status', $classroom?->status?->value ?? 'active')" required />
            <x-input-error :messages="$errors->get('status')" class="mt-2" />
        </div>
    </div>

    <div class="flex items-center gap-3">
        <x-primary-button>Enregistrer</x-primary-button>
        <a href="{{ $cancel }}" class="text-sm text-gray-600 hover:underline">Annuler</a>
    </div>
</form>
