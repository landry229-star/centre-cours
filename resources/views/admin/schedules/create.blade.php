<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Ajouter une séance</h2></x-slot>
    <div class="py-8">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <x-flash />
            @include('admin.schedules._form')
        </div>
    </div>
</x-app-layout>
