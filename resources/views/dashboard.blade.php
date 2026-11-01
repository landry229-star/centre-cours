<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $title }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-gray-900">
                <p>Bienvenue, <strong>{{ auth()->user()->name }}</strong>.</p>
                <p class="mt-2 text-sm text-gray-600">
                    Rôle : {{ auth()->user()->role->label() }}
                </p>
            </div>

            @if (auth()->user()->isAdmin())
                {{-- Raccourcis provisoires : la sidebar complète arrive en Phase 14. --}}
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        ['admin.students.index', 'Élèves', 'Inscrire, rechercher, consulter les fiches'],
                        ['admin.guardians.index', 'Parents / Tuteurs', 'Gérer les parents et leurs accès'],
                        ['admin.teachers.index', 'Enseignants', 'Fiches, accès et affectations'],
                        ['admin.classrooms.index', 'Classes', 'Classes, matières et enseignants'],
                        ['admin.subjects.index', 'Matières', 'Catalogue des matières'],
                        ['admin.school-years.index', 'Années scolaires', 'Créer et activer une année'],
                        ['admin.enrollments.index', 'Inscriptions', 'Suivi des inscriptions par année et classe'],
                        ['attendance.index', 'Présences', 'Feuilles d\'appel et pointage par QR Code'],
                        ['grades.index', 'Notes', 'Évaluations, moyennes et classement'],
                        ['admin.schedules.index', 'Emploi du temps', 'Planifier les cours et éviter les conflits'],
                        ['admin.payments.index', 'Paiements', 'Encaisser, consulter, imprimer les reçus'],
                        ['admin.payments.arrears', 'Impayés', 'Mensualités échues non soldées'],
                        ['admin.fee-schedules.index', 'Tarifs mensuels', 'Montant par niveau et par année'],
                    ] as [$route, $label, $hint])
                        <a href="{{ route($route) }}" class="bg-white p-6 shadow-sm sm:rounded-lg hover:bg-gray-50">
                            <p class="font-semibold text-gray-800">{{ $label }}</p>
                            <p class="text-sm text-gray-500">{{ $hint }}</p>
                        </a>
                    @endforeach
                </div>
            @endif

            @if (auth()->user()->isTeacher())
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <a href="{{ route('attendance.create') }}" class="bg-white p-6 shadow-sm sm:rounded-lg hover:bg-gray-50">
                        <p class="font-semibold text-gray-800">Prendre la présence</p>
                        <p class="text-sm text-gray-500">Ouvrir la feuille d'appel d'une de vos classes</p>
                    </a>
                    <a href="{{ route('attendance.index') }}" class="bg-white p-6 shadow-sm sm:rounded-lg hover:bg-gray-50">
                        <p class="font-semibold text-gray-800">Mes séances</p>
                        <p class="text-sm text-gray-500">Historique de vos feuilles d'appel</p>
                    </a>
                    <a href="{{ route('grades.index') }}" class="bg-white p-6 shadow-sm sm:rounded-lg hover:bg-gray-50">
                        <p class="font-semibold text-gray-800">Notes</p>
                        <p class="text-sm text-gray-500">Créer une évaluation, saisir les notes et consulter le classement</p>
                    </a>
                    <a href="{{ route('teacher.schedules.index') }}" class="bg-white p-6 shadow-sm sm:rounded-lg hover:bg-gray-50">
                        <p class="font-semibold text-gray-800">Mon emploi du temps</p>
                        <p class="text-sm text-gray-500">Voir mes séances dans les classes où j’enseigne</p>
                    </a>
                </div>
            @endif

            @if (auth()->user()->isGuardian())
                <a href="{{ route('guardian.attendance.index') }}" class="block rounded-lg bg-white p-6 shadow-sm hover:bg-gray-50">
                    <p class="font-semibold text-gray-800">Présences de mes enfants</p>
                    <p class="text-sm text-gray-500">Consulter l’historique des appels quotidiens et des séances.</p>
                </a>
                <a href="{{ route('guardian.grades.index') }}" class="block rounded-lg bg-white p-6 shadow-sm hover:bg-gray-50">
                    <p class="font-semibold text-gray-800">Notes de mes enfants</p>
                    <p class="text-sm text-gray-500">Consulter les notes et moyennes de vos enfants.</p>
                </a>
                <a href="{{ route('guardian.schedules.index') }}" class="block rounded-lg bg-white p-6 shadow-sm hover:bg-gray-50">
                    <p class="font-semibold text-gray-800">Emploi du temps de mes enfants</p>
                    <p class="text-sm text-gray-500">Voir les cours de leurs classes pour l’année active.</p>
                </a>
            @endif

            @if (auth()->user()->isStudent())
                <a href="{{ route('student.grades.index') }}" class="block rounded-lg bg-white p-6 shadow-sm hover:bg-gray-50">
                    <p class="font-semibold text-gray-800">Mes notes</p>
                    <p class="text-sm text-gray-500">Consulter mes notes et moyennes par matière.</p>
                </a>
                <a href="{{ route('student.schedules.index') }}" class="block rounded-lg bg-white p-6 shadow-sm hover:bg-gray-50">
                    <p class="font-semibold text-gray-800">Mon emploi du temps</p>
                    <p class="text-sm text-gray-500">Consulter les cours de ma classe.</p>
                </a>
            @endif
        </div>
    </div>
</x-app-layout>
