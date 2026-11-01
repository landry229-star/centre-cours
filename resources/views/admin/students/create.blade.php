<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nouvel élève</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                @include('admin.students._form', [
                    'student' => null,
                    'action' => route('admin.students.store'),
                    'method' => 'POST',
                    'cancel' => route('admin.students.index'),
                ])
                <p class="mt-4 text-xs text-gray-500">Le matricule et le QR Code sont générés automatiquement à l'enregistrement.</p>
            </div>
        </div>
    </div>
</x-app-layout>
