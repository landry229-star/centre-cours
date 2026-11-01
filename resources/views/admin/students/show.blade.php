<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $student->full_name }}</h2>
            @unless ($student->trashed())
                <a href="{{ route('admin.students.edit', $student) }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    Modifier
                </a>
            @endunless
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-flash />

            @if ($student->trashed())
                <div class="rounded-md bg-slate-100 p-4 text-sm text-slate-700" role="status">
                    Cette fiche est archivée depuis le {{ \App\Support\Format::date($student->deleted_at) }}. Elle est consultable en lecture seule.
                </div>
            @endif

            <div class="grid gap-4 md:grid-cols-3">
                <div class="bg-white p-6 shadow-sm sm:rounded-lg flex flex-col items-center text-center">
                    @if (! $student->trashed() && $student->photo_path)
                        <img src="{{ route('students.photo', $student) }}" alt="Photo de {{ $student->full_name }}" class="h-32 w-32 rounded-full object-cover">
                    @else
                        <div class="h-32 w-32 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-3xl font-semibold">
                            {{ mb_substr($student->first_name, 0, 1) }}{{ mb_substr($student->last_name, 0, 1) }}
                        </div>
                    @endif
                    <p class="mt-3 font-mono text-sm">{{ $student->matricule }}</p>
                    <x-badge class="mt-2" :color="$student->status->color()">{{ $student->status->label() }}</x-badge>

                    @if (! $student->trashed())
                        <div class="mt-6 w-44 max-w-full">{!! $qrSvg !!}</div>
                        <p class="mt-1 text-xs text-gray-500">QR Code de présence</p>
                    @endif
                </div>

                <div class="md:col-span-2 space-y-4">
                    <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Informations</h3>
                        <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                            <div><dt class="text-gray-500">Sexe</dt><dd>{{ $student->gender->label() }}</dd></div>
                            <div><dt class="text-gray-500">Date de naissance</dt><dd>{{ \App\Support\Format::date($student->birth_date) }} ({{ $student->age }} ans)</dd></div>
                            <div><dt class="text-gray-500">Lieu de naissance</dt><dd>{{ $student->birth_place ?? '—' }}</dd></div>
                            <div><dt class="text-gray-500">Téléphone</dt><dd>{{ $student->phone ?? '—' }}</dd></div>
                            <div class="sm:col-span-2"><dt class="text-gray-500">Adresse</dt><dd>{{ $student->address ?? '—' }}</dd></div>
                        </dl>
                    </div>

                    <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Tuteur et urgence</h3>
                        <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-gray-500">Parent / tuteur</dt>
                                <dd>
                                    @if ($student->guardian)
                                        <a href="{{ route('admin.guardians.show', $student->guardian) }}" class="text-indigo-700 hover:underline">{{ $student->guardian->full_name }}</a>
                                        ({{ $student->guardian_relationship->label() }})
                                    @endif
                                </dd>
                            </div>
                            <div><dt class="text-gray-500">Téléphone du tuteur</dt><dd>{{ $student->guardian?->phone ?? '—' }}</dd></div>
                            <div><dt class="text-gray-500">Contact d'urgence</dt><dd>{{ $student->emergency_contact_name ?? '—' }}</dd></div>
                            <div><dt class="text-gray-500">Téléphone d'urgence</dt><dd>{{ $student->emergency_contact_phone ?? '—' }}</dd></div>
                        </dl>
                    </div>

                    @include('admin.enrollments._history', ['student' => $student])

                    @unless ($student->trashed())
                        <form method="POST" action="{{ route('admin.students.destroy', $student) }}"
                              onsubmit="return confirm('Archiver cet élève ? Son historique sera conservé.');">
                            @csrf
                            @method('DELETE')
                            <x-danger-button>Archiver cet élève</x-danger-button>
                        </form>
                    @endunless
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
