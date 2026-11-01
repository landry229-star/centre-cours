<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nouvelle année scolaire</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                @include('admin.school-years._form', [
                    'year' => null,
                    'action' => route('admin.school-years.store'),
                    'method' => 'POST',
                ])
                <p class="mt-4 text-xs text-gray-500">L'année est créée inactive. Les périodes de deux années ne peuvent pas se chevaucher.</p>
            </div>
        </div>
    </div>
</x-app-layout>
