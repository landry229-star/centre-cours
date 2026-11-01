<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Modifier la matière</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.subjects.update', $subject) }}" class="bg-white p-6 shadow-sm sm:rounded-lg space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <x-input-label for="name" value="Nom *" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $subject->name)" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="code" value="Code *" />
                    <x-text-input id="code" name="code" type="text" class="mt-1 block w-full uppercase" :value="old('code', $subject->code)" maxlength="10" required />
                    <x-input-error :messages="$errors->get('code')" class="mt-2" />
                </div>
                <div class="flex items-center gap-3">
                    <x-primary-button>Enregistrer</x-primary-button>
                    <a href="{{ route('admin.subjects.index') }}" class="text-sm text-gray-600 hover:underline">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
