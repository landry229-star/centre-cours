<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Présences de mes enfants</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
            <form method="GET" class="grid gap-3 rounded-lg bg-white p-4 shadow-sm sm:grid-cols-5">
                <select name="student_id" aria-label="Élève"
                        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Tous mes enfants</option>
                    @foreach ($students as $student)
                        <option value="{{ $student->id }}" @selected((string) request('student_id') === (string) $student->id)>
                            {{ $student->full_name }}
                        </option>
                    @endforeach
                </select>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" aria-label="Du"
                       class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" aria-label="Au"
                       class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <select name="status" aria-label="Statut"
                        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Tous les statuts</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
                <x-primary-button>Filtrer</x-primary-button>
            </form>

            <div class="flex flex-wrap items-center gap-2 rounded-lg bg-white p-4 text-sm shadow-sm">
                <strong class="mr-1">{{ $summary['total'] }} pointage(s)</strong>
                @foreach ($statuses as $status)
                    <x-badge :color="$status->color()">{{ $status->label() }} : {{ $summary[$status->value] }}</x-badge>
                @endforeach
                <a href="{{ route('guardian.attendance.export', $filters) }}"
                   class="ml-auto inline-flex items-center rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50">Exporter CSV</a>
                <a href="{{ route('guardian.attendance.index') }}"
                   class="inline-flex items-center justify-center px-3 text-sm text-gray-600 hover:underline">Réinitialiser</a>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Élève</th>
                                <th class="px-4 py-3">Classe</th>
                                <th class="px-4 py-3">Séance</th>
                                <th class="px-4 py-3">Statut</th>
                                <th class="px-4 py-3">Remarque</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($attendances as $attendance)
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-3">{{ \App\Support\Format::date($attendance->session->session_date) }}</td>
                                    <td class="px-4 py-3">{{ $attendance->enrollment->student->full_name }}</td>
                                    <td class="px-4 py-3">{{ $attendance->session->classroom->name }}</td>
                                    <td class="px-4 py-3">
                                        {{ $attendance->session->isDaily() ? 'Appel quotidien' : $attendance->session->subject->name }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-badge :color="$attendance->status->color()">{{ $attendance->status->label() }}</x-badge>
                                        @if ($attendance->arrivalTime())
                                            <span class="ml-1 text-gray-500">{{ $attendance->arrivalTime() }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">{{ $attendance->note ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">Aucune présence enregistrée pour ces élèves.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $attendances->links() }}
        </div>
    </div>
</x-app-layout>
