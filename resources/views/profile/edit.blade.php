<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Mon compte
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            @if (auth()->user()->isAdmin())
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <section class="max-w-xl">
                        <header>
                            <h2 class="text-lg font-medium text-gray-900">Photo de profil</h2>
                            <p class="mt-1 text-sm text-gray-600">Cette photo apparaît uniquement dans votre compte, jamais sur les reçus ou les bulletins.</p>
                        </header>

                        <div class="mt-6 flex items-center gap-4">
                            @if ($user->profile_photo_path)
                                <img src="{{ route('profile.photo') }}" alt="Photo de profil de {{ $user->name }}" class="h-20 w-20 rounded-full object-cover">
                            @else
                                <span class="flex h-20 w-20 items-center justify-center rounded-full bg-teal-100 text-2xl font-semibold text-teal-800">{{ mb_substr($user->name, 0, 1) }}</span>
                            @endif

                            <form method="post" action="{{ route('profile.photo.update') }}" enctype="multipart/form-data" class="space-y-3">
                                @csrf
                                <div>
                                    <label for="profile-photo" class="block text-sm font-medium text-gray-700">Choisir une nouvelle photo</label>
                                    <input id="profile-photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" required class="mt-1 block w-full text-sm text-gray-700">
                                    <p class="mt-1 text-xs text-gray-500">JPG, PNG ou WEBP, 2 Mo maximum.</p>
                                    @error('photo')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <x-primary-button>Enregistrer la photo</x-primary-button>
                                @if (session('status') === 'profile-photo-updated')
                                    <p class="text-sm text-green-700">Photo mise à jour.</p>
                                @endif
                            </form>
                        </div>
                    </section>
                </div>
            @endif

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
