<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold leading-tight text-slate-900">Paramètres du centre</h2>
            <p class="mt-1 text-sm text-slate-600">Gérez le nom et les coordonnées qui apparaissent dans l’application et sur les documents officiels.</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('success'))
            <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <form method="post" action="{{ route('admin.settings.centre.update') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('put')

                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700">Nom du centre <span class="text-rose-600">*</span></label>
                    <input id="name" name="name" type="text" value="{{ old('name', $settings['name']) }}" required maxlength="255" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-700 focus:ring-teal-700">
                    @error('name')
                        <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="address" class="block text-sm font-medium text-slate-700">Adresse</label>
                    <input id="address" name="address" type="text" value="{{ old('address', $settings['address']) }}" maxlength="255" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-700 focus:ring-teal-700">
                    @error('address')
                        <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <label for="phone" class="block text-sm font-medium text-slate-700">Téléphone</label>
                        <input id="phone" name="phone" type="tel" value="{{ old('phone', $settings['phone']) }}" maxlength="50" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-700 focus:ring-teal-700">
                        @error('phone')
                            <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-700">E-mail de contact</label>
                        <input id="email" name="email" type="email" value="{{ old('email', $settings['email']) }}" maxlength="255" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-700 focus:ring-teal-700">
                        @error('email')
                            <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="border-t border-slate-200 pt-6">
                    <h3 class="text-base font-semibold text-slate-900">Logo sur les documents</h3>
                    <p class="mt-1 text-sm text-slate-600">Le logo du centre est intégré aux reçus et aux bulletins. Il est distinct de votre photo de profil.</p>

                    @if ($settings['logo_path'])
                        <img src="{{ route('admin.settings.centre.logo') }}" alt="Logo actuel du centre" class="mt-4 h-20 max-w-48 rounded-lg border border-slate-200 bg-white object-contain p-2">
                    @endif

                    <label for="logo" class="mt-4 block text-sm font-medium text-slate-700">Choisir un logo</label>
                    <input id="logo" name="logo" type="file" accept="image/jpeg,image/png" class="mt-1 block w-full text-sm text-slate-700">
                    <p class="mt-1 text-xs text-slate-500">JPG ou PNG, 2 Mo maximum. Un logo carré ou horizontal est recommandé.</p>
                    @error('logo')
                        <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end border-t border-slate-200 pt-6">
                    <x-primary-button>Enregistrer les paramètres</x-primary-button>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
