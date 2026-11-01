<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Présences</h2>
            <a href="{{ route('attendance.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">Prendre la présence</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-flash />

            <form method="GET" class="bg-white p-4 shadow-sm sm:rounded-lg grid gap-3 sm:grid-cols-5">
                <x-select name="classroom_id" :options="$classrooms" :selected="request('classroom_id')" placeholder="Toutes les classes" />
                <input type="date" name="from" value="{{ request('from') }}" aria-label="Du" class="border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <input type="date" name="to" value="{{ request('to') }}" aria-label="Au" class="border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <select name="status" aria-label="Statut" class="border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Tous les statuts</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
                <div class="flex gap-2">
                    <x-primary-button>Filtrer</x-primary-button>
                    <a href="{{ route('attendance.index') }}" class="inline-flex items-center px-3 text-sm text-gray-600 hover:underline">Réinitialiser</a>
                </div>
            </form>

            <div class="flex flex-wrap items-center gap-2 rounded-lg bg-white p-4 text-sm shadow-sm">
                <strong class="mr-1">{{ $summary['sessions'] }} séance(s) · {{ $summary['total'] }} pointage(s)</strong>
                @foreach ($statuses as $status)
                    <x-badge :color="$status->color()">{{ $status->label() }} : {{ $summary[$status->value] }}</x-badge>
                @endforeach
                <a href="{{ route('attendance.export', $filters) }}"
                   class="ml-auto inline-flex items-center rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50">Exporter CSV</a>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Classe</th>
                            <th class="px-4 py-3">Matière</th>
                            @if (auth()->user()->isAdmin())<th class="px-4 py-3 hidden md:table-cell">Enseignant</th>@endif
                            <th class="px-4 py-3">Pointage</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($sessions as $session)
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <a href="{{ route('attendance.show', $session) }}" class="font-medium text-indigo-700 hover:underline">{{ \App\Support\Format::date($session->session_date) }}</a>
                                    @unless ($session->isDaily())
                                        <span class="block text-xs text-gray-500">{{ $session->startTime() }}{{ $session->endTime() ? ' – '.$session->endTime() : '' }}</span>
                                    @endunless
                                </td>
                                <td class="px-4 py-3">{{ $session->classroom->name }}</td>
                                <td class="px-4 py-3">{{ $session->isDaily() ? 'Appel quotidien' : $session->subject->name }}</td>
                                @if (auth()->user()->isAdmin())<td class="px-4 py-3 hidden md:table-cell">{{ $session->teacher->full_name }}</td>@endif
                                <td class="px-4 py-3 space-x-1 whitespace-nowrap">
                                    <x-badge color="green">{{ $session->present_count }} P</x-badge>
                                    <x-badge color="yellow">{{ $session->late_count }} R</x-badge>
                                    <x-badge color="red">{{ $session->absent_count }} A</x-badge>
                                    <x-badge color="blue">{{ $session->excused_count }} J</x-badge>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Aucune séance. Cliquez sur « Prendre la présence ».</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-gray-500">P = présent · R = retard · A = absent · J = absence justifiée</p>

            {{ $sessions->links() }}
        </div>
    </div>
</x-app-layout>
