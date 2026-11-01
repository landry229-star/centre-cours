@php
    $student = $payment->enrollment->student;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Reçu <span class="font-mono">{{ $payment->receipt_number }}</span>
                @if ($payment->isCancelled())<x-badge color="red" class="ml-2">Annulé</x-badge>@endif
            </h2>
            <div class="flex gap-2">
                <a href="{{ route('payments.receipt', $payment) }}" target="_blank" rel="noopener"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50">Imprimer</a>
                <a href="{{ route('payments.receipt', [$payment, 'download' => 1]) }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">Télécharger le PDF</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-flash />

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500">Élève</dt>
                        <dd><a href="{{ route('admin.students.show', $student) }}" class="text-indigo-700 hover:underline">{{ $student->full_name }}</a>
                            <span class="font-mono text-xs text-gray-500">{{ $student->matricule }}</span></dd></div>
                    <div><dt class="text-gray-500">Classe</dt><dd>{{ $payment->enrollment->classroom->name }} · {{ $payment->enrollment->schoolYear->label }}</dd></div>
                    <div><dt class="text-gray-500">Mois concerné</dt><dd>{{ \App\Support\Format::month($payment->month) }}</dd></div>
                    <div><dt class="text-gray-500">Montant</dt><dd class="text-lg font-semibold">{{ \App\Support\Format::fcfa($payment->amount) }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-gray-500">En lettres</dt><dd>{{ \App\Support\AmountInWords::francs($payment->amount) }}</dd></div>
                    <div><dt class="text-gray-500">Date de paiement</dt><dd>{{ \App\Support\Format::date($payment->paid_at) }}</dd></div>
                    <div><dt class="text-gray-500">Mode</dt><dd>{{ $payment->method->label() }}</dd></div>
                    <div><dt class="text-gray-500">Référence</dt><dd>{{ $payment->reference ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Payeur</dt><dd>{{ $payment->payer_name }}</dd></div>
                    <div><dt class="text-gray-500">Enregistré par</dt><dd>{{ $payment->recorder->name }} · {{ \App\Support\Format::dateTime($payment->created_at) }}</dd></div>
                </dl>
            </div>

            @if ($payment->isCancelled())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    <p class="font-semibold">Paiement annulé le {{ \App\Support\Format::dateTime($payment->cancelled_at) }} par {{ $payment->canceller?->name }}</p>
                    <p>Motif : {{ $payment->cancel_reason }}</p>
                </div>
            @else
                <details class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <summary class="cursor-pointer text-sm font-medium text-red-700">Annuler ce paiement</summary>
                    <form method="POST" action="{{ route('admin.payments.cancel', $payment) }}" class="mt-4 space-y-3"
                          onsubmit="return confirm('Annuler définitivement ce paiement ? Cette action est irréversible.');">
                        @csrf
                        <p class="text-sm text-gray-600">Le paiement n'est jamais supprimé : il reste visible, marqué « Annulé », et la mensualité redevient due.</p>
                        <div>
                            <x-input-label for="reason" value="Motif (obligatoire) *" />
                            <x-text-input id="reason" name="reason" type="text" class="mt-1 block w-full" maxlength="255" required />
                            <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                        </div>
                        <x-danger-button>Annuler le paiement</x-danger-button>
                    </form>
                </details>
            @endif

            <p class="text-sm">
                <a href="{{ route('admin.enrollments.statement', $payment->enrollment) }}" class="text-indigo-700 hover:underline">Voir l'échéancier de l'élève</a>
                · <a href="{{ route('admin.payments.index') }}" class="text-gray-600 hover:underline">Retour aux paiements</a>
            </p>
        </div>
    </div>
</x-app-layout>
