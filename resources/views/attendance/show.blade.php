@php
    // Classes écrites en toutes lettres : Tailwind ne détecte pas les noms de classes construits par morceaux.
    $styles = [
        'present' => ['short' => 'P', 'classes' => 'inline-flex h-9 min-w-[2.75rem] items-center justify-center rounded-md border border-gray-300 px-2 text-sm font-medium text-gray-700 peer-checked:border-green-600 peer-checked:bg-green-600 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500'],
        'late' => ['short' => 'R', 'classes' => 'inline-flex h-9 min-w-[2.75rem] items-center justify-center rounded-md border border-gray-300 px-2 text-sm font-medium text-gray-700 peer-checked:border-yellow-500 peer-checked:bg-yellow-500 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500'],
        'absent' => ['short' => 'A', 'classes' => 'inline-flex h-9 min-w-[2.75rem] items-center justify-center rounded-md border border-gray-300 px-2 text-sm font-medium text-gray-700 peer-checked:border-red-600 peer-checked:bg-red-600 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500'],
        'excused' => ['short' => 'J', 'classes' => 'inline-flex h-9 min-w-[2.75rem] items-center justify-center rounded-md border border-gray-300 px-2 text-sm font-medium text-gray-700 peer-checked:border-blue-600 peer-checked:bg-blue-600 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500'],
    ];
@endphp

<x-app-layout>
    <style>[x-cloak] { display: none !important; }</style>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $session->classroom->name }} · {{ $session->isDaily() ? 'Appel quotidien' : $session->subject->name }}
            </h2>
            <a href="{{ route('attendance.index') }}" class="text-sm text-gray-600 hover:underline">← Toutes les séances</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-flash />

            <div class="bg-white p-4 shadow-sm sm:rounded-lg text-sm flex flex-wrap items-center justify-between gap-3">
                <p>
                    <strong>{{ \App\Support\Format::date($session->session_date) }}</strong>
                    @unless ($session->isDaily())
                        · {{ $session->startTime() }}{{ $session->endTime() ? ' – '.$session->endTime() : '' }}
                    @endunless
                    · {{ $session->teacher->full_name }}
                </p>
                <p class="space-x-1">
                    <x-badge color="green">{{ $summary['present'] }} présent(s)</x-badge>
                    <x-badge color="yellow">{{ $summary['late'] }} retard(s)</x-badge>
                    <x-badge color="red">{{ $summary['absent'] }} absent(s)</x-badge>
                    <x-badge color="blue">{{ $summary['excused'] }} justifié(s)</x-badge>
                </p>
            </div>

            {{-- Pointage par QR Code : lecteur USB / Bluetooth (il « tape » le jeton puis Entrée) ou caméra du navigateur. --}}
            @unless ($session->isDaily())
            <div class="bg-white p-4 shadow-sm sm:rounded-lg space-y-3"
                 x-data="qrScanner(@js(route('attendance.scan', $session)), @js(csrf_token()))">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Pointage par QR Code</h3>
                <form method="POST" action="{{ route('attendance.scan', $session) }}" class="flex gap-2">
                    @csrf
                    <input type="text" name="token" autocomplete="off" maxlength="100" placeholder="Scanner la carte de l'élève…"
                           class="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <x-primary-button>Pointer</x-primary-button>
                </form>
                <x-input-error :messages="$errors->get('token')" />

                <div x-show="supported" x-cloak>
                    <button type="button" @click="open ? stop() : start()" class="text-sm text-indigo-700 hover:underline" x-text="open ? 'Arrêter la caméra' : 'Scanner avec la caméra'"></button>
                    <div x-show="open" class="mt-2">
                        <video x-ref="video" playsinline muted class="w-full max-w-sm rounded-md bg-black"></video>
                    </div>
                    <p class="mt-1 text-sm" :class="ok ? 'text-green-700' : 'text-red-700'" x-text="message" aria-live="polite"></p>
                </div>
            </div>
            @endunless

            <form method="POST" action="{{ route('attendance.update', $session) }}" class="bg-white shadow-sm sm:rounded-lg">
                @csrf
                @method('PUT')

                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 p-4">
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Feuille d'appel ({{ $roster->count() }} élève(s))</h3>
                    <div class="flex gap-2 text-xs">
                        <button type="button" class="rounded border border-gray-300 px-2 py-1 hover:bg-gray-50" onclick="document.querySelectorAll('input[type=radio][value=present]').forEach(r => { r.checked = true; r.dispatchEvent(new Event('change', { bubbles: true })); })">Tous présents</button>
                        <button type="button" class="rounded border border-gray-300 px-2 py-1 hover:bg-gray-50" onclick="document.querySelectorAll('input[type=radio][value=absent]').forEach(r => { r.checked = true; r.dispatchEvent(new Event('change', { bubbles: true })); })">Tous absents</button>
                    </div>
                </div>

                <x-input-error :messages="$errors->get('entries')" class="px-4 pt-3" />

                <ul class="divide-y divide-gray-100">
                    @forelse ($roster as $enrollment)
                        @php
                            $record = $records->get($enrollment->id);
                            $current = old("entries.{$enrollment->id}.status", $record?->status->value ?? 'present');
                        @endphp
                        <li class="px-4 py-3" x-data="{ s: @js($current) }">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900 truncate">{{ $enrollment->student->full_name }}</p>
                                    <p class="text-xs text-gray-500">
                                        <span class="font-mono">{{ $enrollment->student->matricule }}</span>
                                        @unless ($record)<span class="ml-2 text-gray-400">non pointé</span>@endunless
                                    </p>
                                </div>
                                <div class="flex items-center gap-1" role="radiogroup" aria-label="Statut de {{ $enrollment->student->full_name }}">
                                    @foreach ($statuses as $status)
                                        <label class="cursor-pointer" title="{{ $status->label() }}">
                                            <input type="radio" class="peer sr-only" name="entries[{{ $enrollment->id }}][status]" value="{{ $status->value }}" x-model="s">
                                            <span class="{{ $styles[$status->value]['classes'] }}">{{ $styles[$status->value]['short'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <div class="mt-2 grid gap-2 sm:grid-cols-3" x-show="s !== 'present'" x-cloak>
                                <input type="time" name="entries[{{ $enrollment->id }}][arrived_at]" x-show="s === 'late'"
                                       value="{{ old("entries.{$enrollment->id}.arrived_at", $record?->arrivalTime()) }}" aria-label="Heure d'arrivée"
                                       class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <input type="text" name="entries[{{ $enrollment->id }}][note]" maxlength="255" placeholder="Remarque (ex. motif)"
                                       value="{{ old("entries.{$enrollment->id}.note", $record?->note) }}"
                                       class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:col-span-2">
                            </div>
                        </li>
                    @empty
                        <li class="px-4 py-8 text-center text-gray-500">Aucun élève inscrit dans cette classe à cette date.</li>
                    @endforelse
                </ul>

                @if ($roster->isNotEmpty())
                    <div class="sticky bottom-0 flex items-center gap-3 border-t border-gray-100 bg-white p-4">
                        <x-primary-button>Enregistrer la feuille</x-primary-button>
                        <span class="text-xs text-gray-500">P présent · R retard · A absent · J absence justifiée</span>
                    </div>
                @endif
            </form>

            @if ($corrections->isNotEmpty())
                <section class="overflow-hidden rounded-lg bg-white shadow-sm" aria-labelledby="correction-history-title">
                    <div class="border-b border-gray-100 p-4">
                        <h3 id="correction-history-title" class="text-sm font-semibold uppercase tracking-wider text-gray-500">Historique des corrections</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                <tr>
                                    <th class="px-4 py-3">Date</th>
                                    <th class="px-4 py-3">Élève</th>
                                    <th class="px-4 py-3">Correction</th>
                                    <th class="px-4 py-3">Par</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($corrections as $correction)
                                    <tr>
                                        <td class="whitespace-nowrap px-4 py-3">{{ \App\Support\Format::dateTime($correction->corrected_at) }}</td>
                                        <td class="px-4 py-3">{{ $correction->attendance->enrollment->student->full_name }}</td>
                                        <td class="px-4 py-3">
                                            {{ $correction->before_status->label() }}{{ $correction->before_arrived_at ? ' · '.substr((string) $correction->before_arrived_at, 0, 5) : '' }}{{ $correction->before_note ? ' · '.$correction->before_note : '' }}
                                            →
                                            {{ $correction->after_status->label() }}{{ $correction->after_arrived_at ? ' · '.substr((string) $correction->after_arrived_at, 0, 5) : '' }}{{ $correction->after_note ? ' · '.$correction->after_note : '' }}
                                        </td>
                                        <td class="px-4 py-3">{{ $correction->corrector->name }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </div>
    </div>

    <script>
        // Scanner QR par la caméra, via l'API native BarcodeDetector (Chrome / Edge / Android).
        // Si le navigateur ne la propose pas, le bloc est masqué : le lecteur USB et la saisie restent disponibles.
        document.addEventListener('alpine:init', () => {
            Alpine.data('qrScanner', (url, csrf) => ({
                supported: 'BarcodeDetector' in window && !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia),
                open: false, ok: true, message: '', last: '', stream: null, timer: null,
                async start() {
                    try {
                        const detector = new BarcodeDetector({ formats: ['qr_code'] });
                        this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                        this.open = true;
                        await this.$nextTick();
                        this.$refs.video.srcObject = this.stream;
                        await this.$refs.video.play();
                        this.timer = setInterval(async () => {
                            try {
                                const codes = await detector.detect(this.$refs.video);
                                if (codes.length && codes[0].rawValue !== this.last) {
                                    this.last = codes[0].rawValue;
                                    await this.send(this.last);
                                    setTimeout(() => { this.last = ''; }, 3000);
                                }
                            } catch (e) { /* image non lisible : on réessaie */ }
                        }, 400);
                    } catch (e) {
                        this.ok = false; this.message = "Caméra indisponible ou refusée.";
                    }
                },
                stop() {
                    clearInterval(this.timer);
                    if (this.stream) this.stream.getTracks().forEach(t => t.stop());
                    this.stream = null; this.open = false;
                },
                async send(token) {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({ token }),
                    });
                    const data = await response.json().catch(() => ({ ok: false, message: 'Erreur de communication.' }));
                    this.ok = !!data.ok; this.message = data.message || '';
                    if (data.ok) {
                        // Met la feuille à jour sans recharger la page (et sans perdre les saisies en cours).
                        const radio = document.querySelector(`input[name="entries[${data.enrollment_id}][status]"][value="${data.status}"]`);
                        if (radio) { radio.checked = true; radio.dispatchEvent(new Event('change', { bubbles: true })); }
                    }
                },
            }));
        });
    </script>
</x-app-layout>
