@php
    $patterns = [
        '0' => 'nnnwwnwnn', '1' => 'wnnwnnnnw', '2' => 'nnwwnnnnw', '3' => 'wnwwnnnnn',
        '4' => 'nnnwwnnnw', '5' => 'wnnwwnnnn', '6' => 'nnwwwnnnn', '7' => 'nnnwnnwnw',
        '8' => 'wnnwnnwnn', '9' => 'nnwwnnwnn', 'A' => 'wnnnnwnnw', 'B' => 'nnwnnwnnw',
        'C' => 'wnwnnwnnn', 'D' => 'nnnnwwnnw', 'E' => 'wnnnwwnnn', 'F' => 'nnwnwwnnn',
        'G' => 'nnnnnwwnw', 'H' => 'wnnnnwwnn', 'I' => 'nnwnnwwnn', 'J' => 'nnnnwwwnn',
        'K' => 'wnnnnnnww', 'L' => 'nnwnnnnww', 'M' => 'wnwnnnnwn', 'N' => 'nnnnwnnww',
        'O' => 'wnnnwnnwn', 'P' => 'nnwnwnnwn', 'Q' => 'nnnnnnwww', 'R' => 'wnnnnnwwn',
        'S' => 'nnwnnnwwn', 'T' => 'nnnnwnwwn', 'U' => 'wwnnnnnnw', 'V' => 'nwwnnnnnw',
        'W' => 'wwwnnnnnn', 'X' => 'nwnnwnnnw', 'Y' => 'wwnnwnnnn', 'Z' => 'nwwnwnnnn',
        '-' => 'nwnnnnwnw', '.' => 'wwnnnnwnn', ' ' => 'nwwnnnwnn', '$' => 'nwnwnwnnn',
        '/' => 'nwnwnnnwn', '+' => 'nwnnnwnwn', '%' => 'nnnwnwnwn', '*' => 'nwnnwnwnn',
    ];

    $barcodeSvg = function (?string $raw) use ($patterns) {
        $text = strtoupper((string) $raw);
        $text = preg_replace('/[^0-9A-Z\-\.\ \$\/\+\%]/', '-', $text);
        $encoded = '*' . $text . '*';
        $x = 0;
        $bars = '';
        $height = 46;

        foreach (str_split($encoded) as $char) {
            $pattern = $patterns[$char] ?? $patterns['-'];

            foreach (str_split($pattern) as $index => $widthCode) {
                $width = $widthCode === 'w' ? 3 : 1;

                if ($index % 2 === 0) {
                    $bars .= '<rect x="' . $x . '" y="0" width="' . $width . '" height="' . $height . '" fill="#000" />';
                }

                $x += $width;
            }

            $x += 1;
        }

        return '<svg viewBox="0 0 ' . $x . ' ' . $height . '" preserveAspectRatio="none">' . $bars . '</svg>';
    };

    $qrKey = $qrField['key'] ?? null;
@endphp
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Штрихкоды - {{ $directory->name }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #000; background: #fff; margin: 12px; }
        .no-print { margin-bottom: 14px; }
        .print-btn { padding: 8px 14px; border: 1px solid #000; background: #fff; cursor: pointer; }
        .labels { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .label { border: 1px solid #000; padding: 8px; min-height: 92px; page-break-inside: avoid; }
        .title { font-size: 12px; font-weight: bold; margin-bottom: 6px; min-height: 28px; }
        .barcode { height: 46px; margin-bottom: 5px; }
        .barcode svg { width: 100%; height: 100%; display: block; }
        .code { font-size: 11px; text-align: center; letter-spacing: 1px; }
        @media print {
            .no-print { display: none; }
            body { margin: 8mm; }
            .labels { gap: 5mm; }
        }
    </style>
</head>
<body>
<div class="no-print">
    <button class="print-btn" onclick="window.print()">Печать штрихкодов</button>
</div>

<div class="labels">
    @foreach($values as $value)
        @php
            $data = is_array($value->data) ? $value->data : [];
            $code = $qrKey ? ($data[$qrKey] ?? null) : null;
            $code = $code ?: ($value->code ?: $value->value);
        @endphp
        <div class="label">
            <div class="title">{{ $value->value }}</div>
            <div class="barcode">{!! $barcodeSvg($code) !!}</div>
            <div class="code">{{ $code }}</div>
        </div>
    @endforeach
</div>
</body>
</html>
