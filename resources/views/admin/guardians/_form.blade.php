@php $guardian = $guardian ?? null; @endphp

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="last_name" value="Nom *" />
            <x-text-input id="last_name" name="last_name" type="text" class="mt-1 block w-full uppercase" :value="old('last_name', $guardian?->last_name)" required autofocus />
            <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="first_name" value="Prénom(s) *" />
            <x-text-input id="first_name" name="first_name" type="text" class="mt-1 block w-full" :value="old('first_name', $guardian?->first_name)" required />
            <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="phone" value="Téléphone *" />
            <x-text-input id="phone" name="phone" type="tel" class="mt-1 block w-full" :value="old('phone', $guardian?->phone)" placeholder="+229 01 00 00 00 00" required />
            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $guardian?->email)" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="profession" value="Profession" />
            <x-text-input id="profession" name="profession" type="text" class="mt-1 block w-full" :value="old('profession', $guardian?->profession)" />
            <x-input-error :messages="$errors->get('profession')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="address" value="Adresse" />
            <x-text-input id="address" name="address" type="text" class="mt-1 block w-full" :value="old('address', $guardian?->address)" />
            <x-input-error :messages="$errors->get('address')" class="mt-2" />
        </div>
    </div>

    @if (! $guardian)
        <label class="flex items-start gap-2 text-sm text-gray-700">
            <input type="checkbox" name="create_account" value="1" class="mt-0.5 rounded border-gray-300" @checked(old('create_account'))>
            <span>Créer un accès à l'espace parent (l'e-mail est alors obligatoire). Un lien pour définir le mot de passe sera envoyé à cette adresse.</span>
        </label>
        <x-input-error :messages="$errors->get('create_account')" class="mt-2" />
    @endif

    <div class="flex items-center gap-3">
        <x-primary-button>Enregistrer</x-primary-button>
        <a href="{{ $cancel }}" class="text-sm text-gray-600 hover:underline">Annuler</a>
    </div>
</form>
