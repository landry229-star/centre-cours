<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wider text-teal-700">Communications</p>
            <h2 class="mt-1 text-2xl font-bold text-slate-900">Nouvelle annonce</h2>
            <p class="mt-1 text-sm text-slate-500">
                {{ $schoolYear ? 'Année scolaire active : '.$schoolYear->label : 'Aucune année scolaire active' }}
            </p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6 lg:px-8">
            <x-flash />
            <form method="POST" action="{{ route('admin.communications.announcements.store') }}" class="grid gap-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                @csrf
                <div>
                    <label for="title" class="block text-sm font-medium text-slate-700">Titre</label>
                    <input id="title" name="title" value="{{ old('title') }}" required maxlength="150" class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-700 focus:ring-teal-700">
                    @error('title')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="classroom_id" class="block text-sm font-medium text-slate-700">Destinataires</label>
                    <select id="classroom_id" name="classroom_id" class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-700 focus:ring-teal-700">
                        <option value="">Toutes les familles</option>
                        @foreach ($classrooms as $classroom)
                            <option value="{{ $classroom->id }}" @selected(old('classroom_id') == $classroom->id)>
                                Familles de {{ $classroom->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('classroom_id')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                    @error('audience')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="body" class="block text-sm font-medium text-slate-700">Message</label>
                    <textarea id="body" name="body" rows="8" required maxlength="10000" class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-700 focus:ring-teal-700">{{ old('body') }}</textarea>
                    @error('body')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-wrap justify-end gap-3">
                    <a href="{{ route('communications.announcements.index') }}" class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">Annuler</a>
                    <button type="submit" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Publier l’annonce</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
