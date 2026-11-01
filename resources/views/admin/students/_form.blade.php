@php
    $student = $student ?? null;
    $guardianOptions = $guardians->mapWithKeys(fn ($g) => [$g->id => $g->full_name.' · '.$g->phone])->all();
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-8">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <section class="space-y-4">
        <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Identité</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="last_name" value="Nom *" />
                <x-text-input id="last_name" name="last_name" type="text" class="mt-1 block w-full uppercase" :value="old('last_name', $student?->last_name)" required autofocus />
                <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="first_name" value="Prénom(s) *" />
                <x-text-input id="first_name" name="first_name" type="text" class="mt-1 block w-full" :value="old('first_name', $student?->first_name)" required />
                <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="gender" value="Sexe *" />
                <x-select name="gender" class="mt-1" :options="\App\Enums\Gender::options()" :selected="old('gender', $student?->gender?->value)" placeholder="Choisir…" required />
                <x-input-error :messages="$errors->get('gender')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="birth_date" value="Date de naissance *" />
                <x-text-input id="birth_date" name="birth_date" type="date" class="mt-1 block w-full" :value="old('birth_date', $student?->birth_date?->format('Y-m-d'))" required />
                <x-input-error :messages="$errors->get('birth_date')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="birth_place" value="Lieu de naissance" />
                <x-text-input id="birth_place" name="birth_place" type="text" class="mt-1 block w-full" :value="old('birth_place', $student?->birth_place)" />
                <x-input-error :messages="$errors->get('birth_place')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="phone" value="Téléphone de l'élève" />
                <x-text-input id="phone" name="phone" type="tel" class="mt-1 block w-full" :value="old('phone', $student?->phone)" placeholder="+229 01 00 00 00 00" />
                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            </div>
            <div class="sm:col-span-2">
                <x-input-label for="address" value="Adresse" />
                <x-text-input id="address" name="address" type="text" class="mt-1 block w-full" :value="old('address', $student?->address)" />
                <x-input-error :messages="$errors->get('address')" class="mt-2" />
            </div>
        </div>
    </section>

    <section class="space-y-4">
        <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Tuteur et contact d'urgence</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="guardian_id" value="Parent / tuteur *" />
                <x-select name="guardian_id" class="mt-1" :options="$guardianOptions" :selected="old('guardian_id', $student?->guardian_id ?? request('guardian_id'))" placeholder="Choisir un parent…" required />
                <x-input-error :messages="$errors->get('guardian_id')" class="mt-2" />
                <p class="mt-1 text-xs text-gray-500">
                    Parent absent de la liste ? <a href="{{ route('admin.guardians.create') }}" class="text-indigo-700 hover:underline">Créer d'abord le parent</a>.
                </p>
            </div>
            <div>
                <x-input-label for="guardian_relationship" value="Lien avec l'élève *" />
                <x-select name="guardian_relationship" class="mt-1" :options="\App\Enums\GuardianRelationship::options()" :selected="old('guardian_relationship', $student?->guardian_relationship?->value)" placeholder="Choisir…" required />
                <x-input-error :messages="$errors->get('guardian_relationship')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="emergency_contact_name" value="Personne à contacter en urgence" />
                <x-text-input id="emergency_contact_name" name="emergency_contact_name" type="text" class="mt-1 block w-full" :value="old('emergency_contact_name', $student?->emergency_contact_name)" />
                <x-input-error :messages="$errors->get('emergency_contact_name')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="emergency_contact_phone" value="Téléphone d'urgence" />
                <x-text-input id="emergency_contact_phone" name="emergency_contact_phone" type="tel" class="mt-1 block w-full" :value="old('emergency_contact_phone', $student?->emergency_contact_phone)" />
                <x-input-error :messages="$errors->get('emergency_contact_phone')" class="mt-2" />
            </div>
        </div>
    </section>

    <section class="space-y-4">
        <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Photo et statut</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="photo" value="Photo (JPG ou PNG, 2 Mo max)" />
                <input id="photo" name="photo" type="file" accept="image/jpeg,image/png" class="mt-1 block w-full text-sm text-gray-700">
                <x-input-error :messages="$errors->get('photo')" class="mt-2" />
                @if ($student?->photo_path)
                    <div class="mt-3 flex items-center gap-3">
                        <img src="{{ route('students.photo', $student) }}" alt="Photo actuelle" class="h-16 w-16 rounded-full object-cover">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="remove_photo" value="1" class="rounded border-gray-300">
                            Supprimer la photo
                        </label>
                    </div>
                @endif
            </div>
            <div>
                <x-input-label for="status" value="Statut *" />
                <x-select name="status" class="mt-1" :options="\App\Enums\StudentStatus::options()" :selected="old('status', $student?->status?->value ?? 'active')" required />
                <x-input-error :messages="$errors->get('status')" class="mt-2" />
            </div>
        </div>
    </section>

    <div class="flex items-center gap-3">
        <x-primary-button>Enregistrer</x-primary-button>
        <a href="{{ $cancel }}" class="text-sm text-gray-600 hover:underline">Annuler</a>
    </div>
</form>
