<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Reçu {{ $payment->receipt_number }}</title>
    <style>
        @page { margin: 22px 28px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #1f2937; }
        .header { width: 100%; border-bottom: 2px solid #4338ca; padding-bottom: 8px; }
        .header td { vertical-align: middle; }
        .logo { width: 56px; height: 56px; }
        .centre-name { font-size: 16px; font-weight: bold; color: #4338ca; }
        .muted { color: #6b7280; }
        .title { text-align: right; }
        .title .label { font-size: 15px; font-weight: bold; letter-spacing: 1px; }
        .title .number { font-size: 12px; font-family: 'DejaVu Sans Mono', monospace; }
        table.info { width: 100%; margin-top: 12px; border-collapse: collapse; }
        table.info td { padding: 5px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        table.info td.k { width: 26%; color: #6b7280; }
        .amount-box { margin-top: 12px; border: 2px solid #4338ca; padding: 8px 12px; }
        .amount { font-size: 18px; font-weight: bold; }
        .words { margin-top: 3px; font-style: italic; }
        .signatures { width: 100%; margin-top: 18px; }
        .signatures td { width: 50%; height: 56px; vertical-align: top; }
        .footer { margin-top: 8px; font-size: 9px; text-align: center; color: #6b7280; }
        .cancelled { position: fixed; top: 38%; left: 12%; font-size: 90px; font-weight: bold; color: #fca5a5; transform: rotate(-18deg); opacity: .65; }
    </style>
</head>
<body>
    @if ($payment->isCancelled())
        <div class="cancelled">ANNULÉ</div>
    @endif

    <table class="header">
        <tr>
            @if ($logo)
                <td style="width: 64px;"><img class="logo" src="{{ $logo }}" alt=""></td>
            @endif
            <td>
                <div class="centre-name">{{ $centre['name'] }}</div>
                <div class="muted">{{ $centre['address'] }}</div>
                <div class="muted">Tél. : {{ $centre['phone'] }}@if (! empty($centre['email'])) · {{ $centre['email'] }}@endif</div>
            </td>
            <td class="title">
                <div class="label">REÇU DE PAIEMENT</div>
                <div class="number">N° {{ $payment->receipt_number }}</div>
                <div class="muted">Date : {{ \App\Support\Format::date($payment->paid_at) }}</div>
            </td>
        </tr>
    </table>

    <table class="info">
        <tr><td class="k">Élève</td><td><strong>{{ $student->full_name }}</strong> (matricule {{ $student->matricule }})</td></tr>
        <tr><td class="k">Classe</td><td>{{ $classroom->name }} · année scolaire {{ $year->label }}</td></tr>
        <tr><td class="k">Parent / tuteur</td><td>{{ $payment->payer_name }}</td></tr>
        <tr><td class="k">Mois concerné</td><td>{{ \Illuminate\Support\Str::ucfirst(\App\Support\Format::month($payment->month)) }}</td></tr>
        <tr><td class="k">Mode de paiement</td><td>{{ $payment->method->label() }}@if ($payment->reference) · Réf. {{ $payment->reference }}@endif</td></tr>
    </table>

    <div class="amount-box">
        <div class="muted">Montant payé</div>
        <div class="amount">{{ \App\Support\Format::fcfa($payment->amount) }}</div>
        <div class="words">Arrêté le présent reçu à la somme de : {{ $amountInWords }}.</div>
    </div>

    <table class="signatures">
        <tr>
            <td><span class="muted">Enregistré par :</span><br><strong>{{ $payment->recorder->name }}</strong></td>
            <td style="text-align: right;"><span class="muted">Signature et cachet du centre</span></td>
        </tr>
    </table>

    @if ($payment->isCancelled())
        <div class="footer">Paiement annulé le {{ \App\Support\Format::dateTime($payment->cancelled_at) }} — {{ $payment->cancel_reason }}</div>
    @else
        <div class="footer">Ce reçu fait foi du paiement. Document généré électroniquement.</div>
    @endif
</body>
</html>
