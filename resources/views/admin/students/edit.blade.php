<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Modifier : {{ $student->full_name }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <p class="mb-4 text-sm text-gray-600">Matricule : <span class="font-mono">{{ $student->matricule }}</span> (non modifiable)</p>
                @include('admin.students._form', [
                    'student' => $student,
                    'action' => route('admin.students.update', $student),
                    'method' => 'PUT',
                    'cancel' => route('admin.students.show', $student),
                ])
            </div>
        </div>
    </div>
</x-app-layout>
