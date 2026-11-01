<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Modifier : {{ $schoolYear->label }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                @include('admin.school-years._form', [
                    'year' => $schoolYear,
                    'action' => route('admin.school-years.update', $schoolYear),
                    'method' => 'PUT',
                ])
            </div>
        </div>
    </div>
</x-app-layout>
