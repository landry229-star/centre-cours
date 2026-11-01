@php $year = $year ?? null; @endphp

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div>
            <x-input-label for="label" value="Libellé *" />
            <x-text-input id="label" name="label" type="text" class="mt-1 block w-full" :value="old('label', $year?->label)" placeholder="2027-2028" required autofocus />
            <x-input-error :messages="$errors->get('label')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="start_date" value="Début *" />
            <x-text-input id="start_date" name="start_date" type="date" class="mt-1 block w-full" :value="old('start_date', $year?->start_date?->format('Y-m-d'))" required />
            <x-input-error :messages="$errors->get('start_date')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="end_date" value="Fin *" />
            <x-text-input id="end_date" name="end_date" type="date" class="mt-1 block w-full" :value="old('end_date', $year?->end_date?->format('Y-m-d'))" required />
            <x-input-error :messages="$errors->get('end_date')" class="mt-2" />
        </div>
    </div>

    <div class="flex items-center gap-3">
        <x-primary-button>Enregistrer</x-primary-button>
        <a href="{{ route('admin.school-years.index') }}" class="text-sm text-gray-600 hover:underline">Annuler</a>
    </div>
</form>
