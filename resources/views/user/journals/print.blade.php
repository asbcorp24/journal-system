<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Печать журнала — {{ $journal->name }}</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #000;
            background: #fff;
            font-size: 12px;
            margin: 20px;
        }

        h1, h2, h3 {
            margin: 0;
            padding: 0;
        }

        .print-header {
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 12px;
        }

        .print-title {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 6px;
        }

        .print-subtitle {
            font-size: 13px;
            color: #333;
        }

        .print-meta {
            margin-top: 12px;
            font-size: 12px;
        }

        .print-meta div {
            margin-bottom: 3px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
        }

        th, td {
            border: 1px solid #000;
            padding: 5px 6px;
            vertical-align: top;
            word-break: break-word;
        }

        th {
            background: #f0f0f0;
            font-weight: bold;
            text-align: left;
        }

        .small {
            font-size: 10px;
            color: #333;
        }

        .status-submitted {
            font-weight: bold;
            color: #8a5a00;
        }

        .status-approved {
            font-weight: bold;
            color: #006b2e;
        }

        .status-rejected {
            font-weight: bold;
            color: #9b0000;
        }

        .signatures {
            margin-top: 35px;
            display: flex;
            gap: 40px;
        }

        .signature-box {
            width: 260px;
        }

        .signature-line {
            margin-top: 30px;
            border-top: 1px solid #000;
            padding-top: 4px;
            font-size: 11px;
        }

        .no-print {
            margin-bottom: 20px;
        }

        .print-btn {
            padding: 8px 14px;
            border: 1px solid #000;
            background: #fff;
            cursor: pointer;
        }

        @media print {
            @page {
                size: A4 landscape;
                margin: 8mm;
            }

            body {
                margin: 8mm;
            }

            .no-print {
                display: none;
            }

            table {
                page-break-inside: auto;
            }

            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            thead {
                display: table-header-group;
            }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button class="print-btn" onclick="window.print()">
        Печать
    </button>
</div>

<div class="print-header">
    <div class="print-title">
        {{ $journal->name }}
    </div>

    <div class="print-subtitle">
        {{ $journal->description ?: 'Электронный производственный журнал' }}
    </div>

    <div class="print-meta">
        <div>
            <strong>Дата формирования:</strong>
            {{ now()->format('d.m.Y H:i') }}
        </div>

        <div>
            <strong>Пользователь:</strong>
            {{ session('user_name') }}
            /
            {{ session('user_role') }}
        </div>

        <div>
            <strong>Количество записей:</strong>
            {{ $entries->count() }}
        </div>
    </div>
</div>

<table>
    <thead>
    <tr>
        <th style="width: 40px;">№</th>
        <th style="width: 90px;">Дата</th>

        @foreach($schema as $field)
            <th>{{ $field['label'] ?? $field['key'] ?? 'Поле' }}</th>
        @endforeach

        <th>Пользователь</th>
        <th>Подразделение</th>
        <th>Статус</th>
        <th>Проверил</th>
        <th>Комментарий</th>
    </tr>
    </thead>

    <tbody>
    @forelse($entries as $index => $entry)
        <tr>
            <td>{{ $index + 1 }}</td>

            <td>
                {{ $entry->entry_date ? $entry->entry_date->format('d.m.Y') : '—' }}
            </td>

            @foreach($schema as $field)
                @php
                    $key = $field['key'] ?? null;
                    $type = $field['type'] ?? 'string';
                    $value = $key && is_array($entry->data) ? ($entry->data[$key] ?? null) : null;

                    if ($value === null || $value === '') {
                        $displayValue = '—';
                    } elseif ($type === 'directory') {
                        $list = $directoryValues[$field['directory_id'] ?? 0] ?? collect();
                        $directoryItem = $list->firstWhere('id', (int)$value);
                        $displayValue = $directoryItem ? $directoryItem->value : $value;
                    } else {
                        $displayValue = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
                    }
                @endphp

                <td>{{ $displayValue }}</td>
            @endforeach

            <td>
                {{ $entry->user->name ?? '—' }}
            </td>

            <td>
                {{ $entry->division->name ?? '—' }}
            </td>

            <td>
                @if($entry->status === 'approved')
                    <span class="status-approved">Подтверждено</span>
                @elseif($entry->status === 'rejected')
                    <span class="status-rejected">Отклонено</span>
                @else
                    <span class="status-submitted">На проверке</span>
                @endif
            </td>

            <td>
                {{ $entry->checker->name ?? '—' }}

                @if($entry->checked_at)
                    <div class="small">
                        {{ $entry->checked_at->format('d.m.Y H:i') }}
                    </div>
                @endif
            </td>

            <td>
                @if($entry->lastComment)
                    {{ $entry->lastComment->comment }}

                    @if($entry->lastComment->user)
                        <div class="small">
                            {{ $entry->lastComment->user->name }}
                            /
                            {{ $entry->lastComment->created_at->format('d.m.Y H:i') }}
                        </div>
                    @endif
                @else
                    —
                @endif
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="{{ count($schema) + 8 }}" style="text-align:center;">
                Записи не найдены
            </td>
        </tr>
    @endforelse
    </tbody>
</table>

<div class="signatures">
    <div class="signature-box">
        <div class="signature-line">
            Ответственный / подпись
        </div>
    </div>

    <div class="signature-box">
        <div class="signature-line">
            Проверяющий / подпись
        </div>
    </div>
</div>

<script>
    window.addEventListener('load', function () {
        setTimeout(function () {
            window.print();
        }, 300);
    });
</script>

</body>
</html>
