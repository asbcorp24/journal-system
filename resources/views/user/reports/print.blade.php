<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>{{ $printTitle ?? $report->name }}</title>
    <style>
        @page {
            size: A4 {{ ($printOrientation ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait' }};
            margin: 12mm;
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #111827;
            margin: 24px;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 24px;
        }

        .meta {
            margin-bottom: 18px;
            color: #4b5563;
            font-size: 13px;
        }

        .params {
            margin: 0 0 18px;
            padding: 0;
            list-style: none;
            font-size: 13px;
        }

        .params li {
            margin-bottom: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 8px 10px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
        }

        .empty {
            padding: 24px 0;
            color: #6b7280;
        }

        .toolbar {
            margin-bottom: 16px;
        }

        .toolbar button {
            padding: 8px 14px;
            border: 1px solid #111827;
            background: #111827;
            color: #fff;
            border-radius: 6px;
            cursor: pointer;
        }

        @media print {
            .toolbar {
                display: none;
            }

            body {
                margin: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Печать</button>
    </div>

    <h1>{{ $printTitle ?? $report->name }}</h1>

    <div class="meta">
        Сформирован: {{ $printedAt->format('d.m.Y H:i') }}
    </div>

    @if(!empty($params))
        <ul class="params">
            @foreach($params as $key => $value)
                @if($value !== null && $value !== '')
                    <li><strong>{{ $key }}:</strong> {{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value }}</li>
                @endif
            @endforeach
        </ul>
    @endif

    @if(!empty($renderedHtml))
        {!! $renderedHtml !!}
    @elseif(count($columns))
        <table>
            <thead>
            <tr>
                @foreach($columns as $column)
                    <th>{{ $column }}</th>
                @endforeach
            </tr>
            </thead>
            <tbody>
            @foreach($rows as $row)
                <tr>
                    @foreach($columns as $column)
                        <td>{{ $row[$column] ?? '' }}</td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <div class="empty">Данных нет.</div>
    @endif
</body>
</html>
