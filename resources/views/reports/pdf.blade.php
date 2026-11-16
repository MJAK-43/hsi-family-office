<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __($title) }}</title>
    <style>
        @page { margin: 28px 30px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #14213d; }
        h1 { font-size: 18px; margin: 0 0 2px; color: #0b2748; }
        h2 { font-size: 13px; margin: 18px 0 6px; color: #0b2748; border-bottom: 2px solid #d4af37; padding-bottom: 3px; }
        .muted { color: #5b6b82; }
        .header { border-bottom: 1px solid #d9dee7; padding-bottom: 8px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #0b2748; color: #fff; text-align: left; padding: 5px 6px; font-size: 9px; }
        td { padding: 4px 6px; border-bottom: 1px solid #e6e9ef; vertical-align: top; }
        tr:nth-child(even) td { background: #f8f6f0; }
        td.num { text-align: right; white-space: nowrap; }
        .comment { background: #f8f6f0; border-left: 3px solid #d4af37; padding: 6px 10px; margin: 8px 0; }
        .footer { position: fixed; bottom: -14px; left: 0; right: 0; font-size: 8px; color: #8a94a6; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ __($title) }}</h1>
        <div class="muted">{{ __('Fonds familial JOKUNG · HSI') }} — {{ __('Période du :from au :to — montants en :currency', ['from' => $report['period']['from'], 'to' => $report['period']['to'], 'currency' => \App\Support\FundCurrency::code()]) }}</div>
        <div class="muted">{{ __('Généré le :date', ['date' => $generatedAt]) }}</div>
    </div>

    @if ($comment)
        <div class="comment">{{ $comment }}</div>
    @endif

    @foreach ($tables as $section => [$headers, $rows])
        <h2>{{ __($sections[$section] ?? $section) }}</h2>
        @if (empty($rows))
            <p class="muted">{{ __('Aucune donnée pour cette période.') }}</p>
        @else
            <table>
                <thead><tr>@foreach ($headers as $header)<th>{{ \App\Support\FundCurrency::label(__($header)) }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>@foreach ($row as $cell)<td class="{{ is_numeric($cell) ? 'num' : '' }}">{{ is_float($cell) ? number_format($cell, 2, ',', ' ') : (is_string($cell) ? \App\Support\FundCurrency::label($cell) : $cell) }}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach

    <div class="footer">{{ __('Document généré par l’application HSI — confidentiel, réservé aux membres du fonds.') }}</div>
</body>
</html>
