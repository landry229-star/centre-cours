@php
    $user = Auth::user();

    $navigationGroups = match (true) {
        $user->isAdmin() => [
            ['label' => 'Vue générale', 'items' => [
                ['route' => 'admin.dashboard', 'label' => 'Tableau de bord', 'active' => 'admin.dashboard'],
                ['route' => 'admin.settings.centre.edit', 'label' => 'Paramètres du centre', 'active' => 'admin.settings.centre.*'],
                ['route' => 'admin.reports.index', 'label' => 'Pilotage et rapports', 'active' => 'admin.reports.*'],
            ]],
            ['label' => 'Établissement', 'items' => [
                ['route' => 'admin.students.index', 'label' => 'Élèves', 'active' => 'admin.students.*'],
                ['route' => 'admin.guardians.index', 'label' => 'Parents / tuteurs', 'active' => 'admin.guardians.*'],
                ['route' => 'admin.teachers.index', 'label' => 'Enseignants', 'active' => 'admin.teachers.*'],
                ['route' => 'admin.classrooms.index', 'label' => 'Classes', 'active' => 'admin.classrooms.*'],
                ['route' => 'admin.subjects.index', 'label' => 'Matières', 'active' => 'admin.subjects.*'],
                ['route' => 'admin.school-years.index', 'label' => 'Années scolaires', 'active' => 'admin.school-years.*'],
                ['route' => 'admin.academic-periods.index', 'label' => 'Périodes scolaires', 'active' => 'admin.academic-periods.*'],
                ['route' => 'admin.enrollments.index', 'label' => 'Inscriptions', 'active' => 'admin.enrollments.*'],
            ]],
            ['label' => 'Pédagogie', 'items' => [
                ['route' => 'attendance.index', 'label' => 'Présences', 'active' => 'attendance.*'],
                ['route' => 'grades.index', 'label' => 'Notes', 'active' => 'grades.*'],
                ['route' => 'report-cards.index', 'label' => 'Bulletins', 'active' => 'report-cards.*'],
                ['route' => 'admin.schedules.index', 'label' => 'Emploi du temps', 'active' => 'admin.schedules.*'],
            ]],
            ['label' => 'Communications', 'items' => [
                ['route' => 'communications.announcements.index', 'label' => 'Annonces', 'active' => 'communications.announcements.*', 'badge' => $unreadAnnouncementsCount],
                ['route' => 'communications.messages.index', 'label' => 'Messages des familles', 'active' => 'communications.messages.*', 'badge' => $unreadMessagesCount],
            ]],
            ['label' => 'Finances', 'items' => [
                ['route' => 'admin.payments.index', 'label' => 'Paiements', 'active' => 'admin.payments.index'],
                ['route' => 'admin.payments.arrears', 'label' => 'Impayés', 'active' => 'admin.payments.arrears'],
                ['route' => 'admin.fee-schedules.index', 'label' => 'Tarifs', 'active' => 'admin.fee-schedules.*'],
            ]],
        ],
        $user->isTeacher() => [
            ['label' => 'Mon activité', 'items' => [
                ['route' => 'teacher.dashboard', 'label' => 'Tableau de bord', 'active' => 'teacher.dashboard'],
                ['route' => 'attendance.index', 'label' => 'Présences', 'active' => 'attendance.*'],
                ['route' => 'grades.index', 'label' => 'Notes', 'active' => 'grades.*'],
                ['route' => 'teacher.schedules.index', 'label' => 'Emploi du temps', 'active' => 'teacher.schedules.*'],
            ]],
        ],
        $user->isGuardian() => [
            ['label' => 'Espace famille', 'items' => [
                ['route' => 'guardian.dashboard', 'label' => 'Tableau de bord', 'active' => 'guardian.dashboard'],
                ['route' => 'guardian.attendance.index', 'label' => 'Présences', 'active' => 'guardian.attendance.*'],
                ['route' => 'guardian.grades.index', 'label' => 'Notes', 'active' => 'guardian.grades.*'],
                ['route' => 'guardian.payments.index', 'label' => 'Paiements et échéancier', 'active' => 'guardian.payments.*'],
                ['route' => 'report-cards.index', 'label' => 'Bulletins', 'active' => 'report-cards.*'],
                ['route' => 'guardian.schedules.index', 'label' => 'Emploi du temps', 'active' => 'guardian.schedules.*'],
            ]],
            ['label' => 'Communications', 'items' => [
                ['route' => 'communications.announcements.index', 'label' => 'Annonces du centre', 'active' => 'communications.announcements.*', 'badge' => $unreadAnnouncementsCount],
                ['route' => 'communications.messages.index', 'label' => 'Écrire à l’administration', 'active' => 'communications.messages.*', 'badge' => $unreadMessagesCount],
            ]],
        ],
        default => [
            ['label' => 'Mon espace', 'items' => [
                ['route' => 'student.dashboard', 'label' => 'Tableau de bord', 'active' => 'student.dashboard'],
                ['route' => 'student.grades.index', 'label' => 'Mes notes', 'active' => 'student.grades.*'],
                ['route' => 'report-cards.index', 'label' => 'Bulletins', 'active' => 'report-cards.*'],
                ['route' => 'student.schedules.index', 'label' => 'Emploi du temps', 'active' => 'student.schedules.*'],
            ]],
        ],
    };

    $navigationGroups[] = ['label' => 'Compte', 'items' => [
        ['route' => 'notifications.index', 'label' => 'Notifications', 'active' => 'notifications.*', 'badge' => $unreadNotificationsCount],
        ['route' => 'notifications.preferences.edit', 'label' => 'Préférences de notification', 'active' => 'notifications.preferences.*'],
    ]];
@endphp

<aside class="fixed inset-y-0 left-0 z-40 hidden w-72 flex-col border-r border-slate-200 bg-white lg:flex">
    <a href="{{ route('dashboard') }}" class="flex h-20 items-center gap-3 border-b border-slate-100 px-6">
        <x-application-logo class="h-11 w-11 text-teal-700" />
        <span class="text-sm font-bold leading-tight text-slate-900">{{ $centreSettings['name'] }}</span>
    </a>

    <div class="flex-1 overflow-y-auto px-4 py-5">
        @include('layouts.partials.navigation-links', ['groups' => $navigationGroups])
    </div>

    <div class="border-t border-slate-100 p-4">
        <div class="flex items-center gap-3 px-3">
            @if ($user->isAdmin() && $user->profile_photo_path)
                <img src="{{ route('profile.photo') }}" alt="" class="h-10 w-10 rounded-full object-cover">
            @else
                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-teal-100 font-semibold text-teal-800">{{ mb_substr($user->name, 0, 1) }}</span>
            @endif
            <p class="truncate text-sm font-semibold text-slate-800">{{ $user->name }}</p>
        </div>
        <p class="truncate px-3 pt-1 text-xs text-slate-500">{{ $user->email }}</p>
        <div class="mt-3 flex items-center gap-2">
            <a href="{{ route('profile.edit') }}" class="flex-1 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900">Mon profil</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-rose-50 hover:text-rose-700">Déconnexion</button>
            </form>
        </div>
    </div>
</aside>

<div class="sticky top-0 z-40 border-b border-slate-200 bg-white lg:hidden" x-data="{ open: false }">
    <div class="flex h-16 items-center justify-between gap-3 px-4">
        <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-2">
            <x-application-logo class="h-9 w-9 shrink-0 text-teal-700" />
            <span class="truncate text-sm font-bold text-slate-900">{{ $centreSettings['name'] }}</span>
        </a>
        <button type="button" @click="open = ! open" :aria-expanded="open.toString()" aria-controls="mobile-navigation" class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-teal-700">
            <span class="sr-only">Ouvrir le menu</span>
            <svg x-show="! open" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
            <svg x-show="open" x-cloak class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 6 12 12M18 6 6 18"/>
            </svg>
        </button>
    </div>
    <div id="mobile-navigation" x-show="open" x-cloak class="max-h-[calc(100vh-4rem)] overflow-y-auto border-t border-slate-100 bg-white px-4 py-4 shadow-lg">
        @include('layouts.partials.navigation-links', ['groups' => $navigationGroups])
        <div class="mt-4 border-t border-slate-100 pt-4">
            <div class="flex items-center gap-3 px-3">
                @if ($user->isAdmin() && $user->profile_photo_path)
                    <img src="{{ route('profile.photo') }}" alt="" class="h-10 w-10 rounded-full object-cover">
                @else
                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-teal-100 font-semibold text-teal-800">{{ mb_substr($user->name, 0, 1) }}</span>
                @endif
                <p class="text-sm font-semibold text-slate-800">{{ $user->name }}</p>
            </div>
            <div class="mt-2 flex gap-2">
                <a href="{{ route('profile.edit') }}" class="flex-1 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Mon profil</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50">Déconnexion</button>
                </form>
            </div>
        </div>
    </div>
</div>
