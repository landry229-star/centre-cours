<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wider text-teal-700">Communications</p>
            <h2 class="mt-1 text-2xl font-bold text-slate-900">
                {{ auth()->user()->isAdmin() ? 'Écrire à une famille' : 'Écrire à l’administration' }}
            </h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6 lg:px-8">
            <x-flash />
            <form method="POST" action="{{ route('communications.messages.store') }}" class="grid gap-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                @csrf
                @if (auth()->user()->isAdmin())
                    <div>
                        <label for="guardian_id" class="block text-sm font-medium text-slate-700">Famille destinataire</label>
                        <select id="guardian_id" name="guardian_id" required class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-700 focus:ring-teal-700">
                            <option value="">Choisir une famille</option>
                            @foreach ($guardians as $guardian)
                                <option value="{{ $guardian->id }}" @selected(old('guardian_id') == $guardian->id)>
                                    {{ $guardian->full_name }} — {{ $guardian->user->email }}
                                </option>
                            @endforeach
                        </select>
                        @error('guardian_id')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                    </div>
                @endif
                <div>
                    <label for="body" class="block text-sm font-medium text-slate-700">Message</label>
                    <textarea id="body" name="body" rows="8" required maxlength="4000" class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-700 focus:ring-teal-700">{{ old('body') }}</textarea>
                    @error('body')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-wrap justify-end gap-3">
                    <a href="{{ route('communications.messages.index') }}" class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">Annuler</a>
                    <button type="submit" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Envoyer</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
