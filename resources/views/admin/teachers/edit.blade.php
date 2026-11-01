<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Modifier : {{ $teacher->full_name }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <p class="mb-4 text-sm text-gray-600">Matricule : <span class="font-mono">{{ $teacher->matricule }}</span> (non modifiable)</p>
                @include('admin.teachers._form', [
                    'teacher' => $teacher,
                    'action' => route('admin.teachers.update', $teacher),
                    'method' => 'PUT',
                    'cancel' => route('admin.teachers.show', $teacher),
                ])
            </div>
        </div>
    </div>
</x-app-layout>
