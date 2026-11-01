<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Créer une évaluation</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl space-y-4 sm:px-6 lg:px-8">
            <x-flash />
            <form method="POST" action="{{ route('grades.store') }}" class="space-y-5 rounded-lg bg-white p-6 shadow-sm">
                @csrf

                <div>
                    <x-input-label for="assignment_id" value="Classe et matière" />
                    <x-select id="assignment_id" name="assignment_id" :options="$assignments->all()" :selected="old('assignment_id')" placeholder="Choisir une affectation" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('assignment_id')" class="mt-2" />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="title" value="Intitulé" />
                        <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title')" required maxlength="120" placeholder="Ex. Interrogation sur les fractions" />
                        <x-input-error :messages="$errors->get('title')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="type" value="Type d'évaluation" />
                        <x-select id="type" name="type" :options="$types" :selected="old('type')" placeholder="Choisir un type" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('type')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="evaluation_date" value="Date" />
                        <x-text-input id="evaluation_date" name="evaluation_date" type="date" class="mt-1 block w-full" :value="old('evaluation_date', now()->toDateString())" required />
                        <x-input-error :messages="$errors->get('evaluation_date')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="coefficient" value="Coefficient de l'évaluation" />
                        <x-text-input id="coefficient" name="coefficient" type="number" min="1" max="10" class="mt-1 block w-full" :value="old('coefficient', 1)" required />
                        <x-input-error :messages="$errors->get('coefficient')" class="mt-2" />
                    </div>
                </div>

                <p class="text-sm text-gray-600">Toutes les notes sont sur 20. Le coefficient pondère cette évaluation dans la moyenne de la matière.</p>

                <div class="flex flex-wrap gap-3">
                    <x-primary-button>Créer l'évaluation</x-primary-button>
                    <a href="{{ route('grades.index') }}" class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
