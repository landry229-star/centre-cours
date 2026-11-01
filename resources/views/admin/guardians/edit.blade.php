<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Modifier : {{ $guardian->full_name }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                @include('admin.guardians._form', [
                    'guardian' => $guardian,
                    'action' => route('admin.guardians.update', $guardian),
                    'method' => 'PUT',
                    'cancel' => route('admin.guardians.show', $guardian),
                ])
            </div>
        </div>
    </div>
</x-app-layout>
