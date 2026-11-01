@php $teacher = $teacher ?? null; @endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-8">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <section class="space-y-4">
        <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Identité et contact</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="last_name" value="Nom *" />
                <x-text-input id="last_name" name="last_name" type="text" class="mt-1 block w-full uppercase" :value="old('last_name', $teacher?->last_name)" required autofocus />
                <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="first_name" value="Prénom(s) *" />
                <x-text-input id="first_name" name="first_name" type="text" class="mt-1 block w-full" :value="old('first_name', $teacher?->first_name)" required />
                <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="gender" value="Sexe *" />
                <x-select name="gender" class="mt-1" :options="\App\Enums\Gender::options()" :selected="old('gender', $teacher?->gender?->value)" placeholder="Choisir…" required />
                <x-input-error :messages="$errors->get('gender')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="phone" value="Téléphone *" />
                <x-text-input id="phone" name="phone" type="tel" class="mt-1 block w-full" :value="old('phone', $teacher?->phone)" placeholder="+229 01 00 00 00 00" required />
                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="email" value="Email" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $teacher?->email)" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="address" value="Adresse" />
                <x-text-input id="address" name="address" type="text" class="mt-1 block w-full" :value="old('address', $teacher?->address)" />
                <x-input-error :messages="$errors->get('address')" class="mt-2" />
            </div>
        </div>
    </section>

    <section class="space-y-4">
        <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Profil professionnel</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="specialty" value="Spécialité *" />
                <x-text-input id="specialty" name="specialty" type="text" class="mt-1 block w-full" :value="old('specialty', $teacher?->specialty)" placeholder="Mathématiques" required />
                <x-input-error :messages="$errors->get('specialty')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="education_level" value="Niveau d'étude *" />
                <x-select name="education_level" class="mt-1" :options="\App\Enums\EducationLevel::options()" :selected="old('education_level', $teacher?->education_level?->value)" placeholder="Choisir…" required />
                <x-input-error :messages="$errors->get('education_level')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="hired_at" value="Date de recrutement" />
                <x-text-input id="hired_at" name="hired_at" type="date" class="mt-1 block w-full" :value="old('hired_at', $teacher?->hired_at?->format('Y-m-d'))" />
                <x-input-error :messages="$errors->get('hired_at')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="employment_ended_at" value="Date de fin d’emploi" />
                <x-text-input id="employment_ended_at" name="employment_ended_at" type="date" class="mt-1 block w-full" :value="old('employment_ended_at', $teacher?->employment_ended_at?->format('Y-m-d'))" />
                <x-input-error :messages="$errors->get('employment_ended_at')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="status" value="Statut *" />
                <x-select name="status" class="mt-1" :options="\App\Enums\TeacherStatus::options()" :selected="old('status', $teacher?->status?->value ?? 'active')" required />
                <x-input-error :messages="$errors->get('status')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="emergency_contact_name" value="Contact d’urgence" />
                <x-text-input id="emergency_contact_name" name="emergency_contact_name" type="text" class="mt-1 block w-full" :value="old('emergency_contact_name', $teacher?->emergency_contact_name)" />
                <x-input-error :messages="$errors->get('emergency_contact_name')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="emergency_contact_phone" value="Téléphone d’urgence" />
                <x-text-input id="emergency_contact_phone" name="emergency_contact_phone" type="tel" class="mt-1 block w-full" :value="old('emergency_contact_phone', $teacher?->emergency_contact_phone)" placeholder="+229 01 00 00 00 00" />
                <x-input-error :messages="$errors->get('emergency_contact_phone')" class="mt-2" />
            </div>
            <div class="sm:col-span-2">
                <x-input-label for="photo" value="Photo (JPG ou PNG, 2 Mo max)" />
                <input id="photo" name="photo" type="file" accept="image/jpeg,image/png" class="mt-1 block w-full text-sm text-gray-700">
                <x-input-error :messages="$errors->get('photo')" class="mt-2" />
                @if ($teacher?->photo_path)
                    <div class="mt-3 flex items-center gap-3">
                        <img src="{{ route('teachers.photo', $teacher) }}" alt="Photo actuelle" class="h-16 w-16 rounded-full object-cover">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="remove_photo" value="1" class="rounded border-gray-300">
                            Supprimer la photo
                        </label>
                    </div>
                @endif
            </div>
        </div>
    </section>

    @if (! $teacher)
        <label class="flex items-start gap-2 text-sm text-gray-700">
            <input type="checkbox" name="create_account" value="1" class="mt-0.5 rounded border-gray-300" @checked(old('create_account'))>
            <span>Créer un accès à l'espace enseignant (l'e-mail est alors obligatoire). Un lien pour définir le mot de passe sera envoyé à cette adresse.</span>
        </label>
        <x-input-error :messages="$errors->get('create_account')" class="mt-2" />
    @endif

    <div class="flex items-center gap-3">
        <x-primary-button>Enregistrer</x-primary-button>
        <a href="{{ $cancel }}" class="text-sm text-gray-600 hover:underline">Annuler</a>
    </div>
</form>
