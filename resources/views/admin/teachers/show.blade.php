<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $teacher->full_name }}</h2>
            <a href="{{ route('admin.teachers.edit', $teacher) }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                Modifier
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-flash />

            @if (str_starts_with($teacher->matricule, 'PERF26-ENS-'))
                <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
                    Profil de simulation PERF26 : identité, affectations et volumes horaires fictifs, utilisés pour les essais de performance. Ils ne représentent pas la répartition réelle du centre.
                </div>
            @endif

            <div class="grid gap-4 md:grid-cols-3">
                <div class="bg-white p-6 shadow-sm sm:rounded-lg flex flex-col items-center text-center">
                    @if ($teacher->photo_path)
                        <img src="{{ route('teachers.photo', $teacher) }}" alt="Photo de {{ $teacher->full_name }}" class="h-32 w-32 rounded-full object-cover">
                    @else
                        <div class="h-32 w-32 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-3xl font-semibold">
                            {{ mb_substr($teacher->first_name, 0, 1) }}{{ mb_substr($teacher->last_name, 0, 1) }}
                        </div>
                    @endif
                    <p class="mt-3 font-mono text-sm">{{ $teacher->matricule }}</p>
                    <x-badge class="mt-2" :color="$teacher->status->color()">{{ $teacher->status->label() }}</x-badge>
                </div>

                <div class="md:col-span-2 space-y-4">
                    <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Informations</h3>
                        <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                            <div><dt class="text-gray-500">Sexe</dt><dd>{{ $teacher->gender->label() }}</dd></div>
                            <div><dt class="text-gray-500">Spécialité</dt><dd>{{ $teacher->specialty }}</dd></div>
                            <div><dt class="text-gray-500">Niveau d'étude</dt><dd>{{ $teacher->education_level->label() }}</dd></div>
                            <div><dt class="text-gray-500">Recruté le</dt><dd>{{ $teacher->hired_at ? \App\Support\Format::date($teacher->hired_at) : '—' }}</dd></div>
                            <div><dt class="text-gray-500">Fin d’emploi</dt><dd>{{ $teacher->employment_ended_at ? \App\Support\Format::date($teacher->employment_ended_at) : '—' }}</dd></div>
                            <div><dt class="text-gray-500">Téléphone</dt><dd>{{ $teacher->phone }}</dd></div>
                            <div><dt class="text-gray-500">Email</dt><dd>{{ $teacher->email ?? '—' }}</dd></div>
                            <div><dt class="text-gray-500">Contact d’urgence</dt><dd>{{ $teacher->emergency_contact_name ?? '—' }}</dd></div>
                            <div><dt class="text-gray-500">Téléphone d’urgence</dt><dd>{{ $teacher->emergency_contact_phone ?? '—' }}</dd></div>
                            <div class="sm:col-span-2"><dt class="text-gray-500">Adresse</dt><dd>{{ $teacher->address ?? '—' }}</dd></div>
                        </dl>
                    </div>

                    <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Documents administratifs</h3>
                        <form method="POST" action="{{ route('admin.teachers.documents.store', $teacher) }}" enctype="multipart/form-data" class="mt-4 grid gap-4 sm:grid-cols-2">
                            @csrf
                            <div>
                                <x-input-label for="document_type" value="Type de document *" />
                                <x-select name="type" class="mt-1" :options="\App\Enums\TeacherDocumentType::options()" :selected="old('type')" placeholder="Choisir…" required />
                                <x-input-error :messages="$errors->get('type')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="document_date" value="Date du document" />
                                <x-text-input id="document_date" name="document_date" type="date" class="mt-1 block w-full" :value="old('document_date')" />
                                <x-input-error :messages="$errors->get('document_date')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="expires_at" value="Date d’expiration" />
                                <x-text-input id="expires_at" name="expires_at" type="date" class="mt-1 block w-full" :value="old('expires_at')" />
                                <x-input-error :messages="$errors->get('expires_at')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="file" value="Fichier (PDF, JPG ou PNG, 10 Mo max) *" />
                                <input id="file" name="file" type="file" accept="application/pdf,image/jpeg,image/png" required class="mt-1 block w-full text-sm text-gray-700">
                                <x-input-error :messages="$errors->get('file')" class="mt-2" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-primary-button>Ajouter le document</x-primary-button>
                                <p class="mt-2 text-xs text-gray-500">Fichier stocké en privé, accessible uniquement depuis l’administration.</p>
                            </div>
                        </form>

                        <ul class="mt-5 divide-y divide-gray-100 text-sm">
                            @forelse ($teacher->documents as $document)
                                <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                                    <div>
                                        <p class="font-medium text-gray-800">
                                            {{ $document->type->label() }} · {{ $document->original_filename }}
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            Document du {{ $document->document_date ? \App\Support\Format::date($document->document_date) : 'date non renseignée' }}
                                            @if ($document->expires_at)
                                                · expire le {{ \App\Support\Format::date($document->expires_at) }}
                                            @endif
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            Ajouté le {{ \App\Support\Format::date($document->created_at) }} par {{ $document->uploader->name }}
                                        </p>
                                    </div>
                                    <a href="{{ route('admin.teachers.documents.show', [$teacher, $document]) }}" class="font-semibold text-indigo-700 hover:underline">
                                        Télécharger
                                    </a>
                                </li>
                            @empty
                                <li class="py-3 text-gray-500">Aucun document enregistré.</li>
                            @endforelse
                        </ul>
                    </section>

                    <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Accès à l'espace enseignant</h3>
                        <div class="mt-3 text-sm">
                            @if ($teacher->user)
                                <x-badge :color="$teacher->user->is_active ? 'green' : 'red'">
                                    {{ $teacher->user->is_active ? 'Accès actif' : 'Accès désactivé' }}
                                </x-badge>
                                <span class="ml-2 text-gray-600">Identifiant : {{ $teacher->user->email }}</span>
                                @if ($teacher->user->is_active && $teacher->user->must_change_password)
                                    <p class="mt-2 text-sm text-amber-700">En attente de définition du mot de passe par l’enseignant. Aucun mot de passe initial n’est affiché.</p>
                                    <form method="POST" action="{{ route('admin.teachers.account.resend-setup-link', $teacher) }}" class="mt-3">
                                        @csrf
                                        <x-secondary-button type="submit">Renvoyer le lien de définition</x-secondary-button>
                                    </form>
                                @elseif (! $teacher->user->is_active && $teacher->status === \App\Enums\TeacherStatus::Active && $teacher->employment_ended_at === null)
                                    <form method="POST" action="{{ route('admin.teachers.account.update', $teacher) }}" class="mt-3"
                                          onsubmit="return confirm('Réactiver l’accès de cet enseignant ?');">
                                        @csrf
                                        @method('PATCH')
                                        <x-secondary-button type="submit">Réactiver l’accès</x-secondary-button>
                                    </form>
                                @elseif (! $teacher->user->is_active)
                                    <p class="mt-2 text-sm text-gray-600">L’accès ne pourra être réactivé qu’après la remise en activité de l’enseignant.</p>
                                @else
                                    <p class="mt-2 text-sm text-gray-600">L’enseignant définit et gère son mot de passe depuis son compte.</p>
                                @endif
                            @elseif ($teacher->email)
                                @if ($teacher->status === \App\Enums\TeacherStatus::Active && $teacher->employment_ended_at === null)
                                    <p class="text-gray-600">Aucun accès. La création envoie un lien de définition à {{ $teacher->email }}; le mot de passe n’est jamais communiqué par l’administration.</p>
                                    <form method="POST" action="{{ route('admin.teachers.account.store', $teacher) }}" class="mt-3"
                                          onsubmit="return confirm('Créer l’accès et envoyer le lien de définition à l’adresse e-mail enregistrée ?');">
                                        @csrf
                                        <x-secondary-button type="submit">Créer l’accès</x-secondary-button>
                                    </form>
                                @else
                                    <p class="text-gray-600">Un accès ne peut être créé que pour un enseignant en activité.</p>
                                @endif
                            @else
                                <p class="text-gray-600">Ajoutez une adresse email pour pouvoir créer un accès.</p>
                            @endif
                        </div>
                    </div>

                    <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Affectations ({{ $assignmentCount }})</h3>
                        <p class="mt-2 text-sm font-medium text-gray-800">{{ $weeklyHoursTotal }} h/semaine déclarées</p>
                        @if ($unreportedHoursCount > 0)
                            <p class="mt-1 text-xs text-gray-500">{{ $unreportedHoursCount }} affectation(s) sans volume horaire renseigné.</p>
                        @endif

                        @forelse ($assignmentGroups as $schoolYearId => $group)
                            <section class="mt-4 rounded-md border border-gray-200 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <h4 class="font-semibold text-gray-800">{{ $group['school_year']->label }}</h4>
                                        <p class="text-sm text-gray-600">
                                            {{ $group['count'] }} affectation(s) ·
                                            {{ $group['weekly_hours'] }} h/semaine déclarées
                                            @if ($group['unreported_hours'] > 0)
                                                · {{ $group['unreported_hours'] }} sans horaire
                                            @endif
                                        </p>
                                    </div>
                                    <a href="{{ route('admin.schedules.index', ['school_year_id' => $schoolYearId, 'teacher_id' => $teacher->id]) }}"
                                       class="text-sm font-semibold text-indigo-700 hover:underline">
                                        Voir son emploi du temps
                                    </a>
                                </div>

                                <details class="mt-3">
                                    <summary class="cursor-pointer text-sm font-medium text-gray-700">Afficher les affectations</summary>
                                    <ul class="mt-2 divide-y divide-gray-100 text-sm">
                                        @foreach ($group['assignments'] as $assignment)
                                            <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                                                <span>
                                                    {{ $assignment->subject->name }} ·
                                                    <a href="{{ route('admin.classrooms.show', $assignment->classroom_id) }}" class="text-indigo-700 hover:underline">
                                                        {{ $assignment->classroom->name }}
                                                    </a>
                                                </span>
                                                <span class="text-xs text-gray-500">
                                                    {{ $assignment->weekly_hours !== null ? $assignment->weekly_hours.' h/sem.' : 'Horaire non renseigné' }}
                                                    · coef. {{ $assignment->coefficient }}
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            </section>
                        @empty
                            <p class="mt-3 text-sm text-gray-500">Aucune affectation pour le moment.</p>
                        @endforelse
                    </div>

                    <form method="POST" action="{{ route('admin.teachers.destroy', $teacher) }}"
                          onsubmit="return confirm('Archiver cet enseignant ? Son accès sera désactivé.');">
                        @csrf
                        @method('DELETE')
                        <x-danger-button>Archiver cet enseignant</x-danger-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
