<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Печать журнала — {{ $printTemplate->name ?? $journal->name }}</title>

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

        .template-entry {
            margin-top: 14px;
            page-break-inside: avoid;
        }

        .template-entry + .template-entry {
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px dashed #999;
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
                size: A4 {{ ($printSettings['orientation'] ?? 'landscape') === 'portrait' ? 'portrait' : 'landscape' }};
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
        {{ ($printTemplate->title ?? null) ?: $journal->name }}
    </div>

    <div class="print-subtitle">
        {{ ($printTemplate->description ?? null) ?: ($journal->description ?: 'Электронный производственный журнал') }}
    </div>

    <div class="print-meta">
        @if($printTemplate)
            <div>
                <strong>Шаблон печати:</strong>
                {{ $printTemplate->name }}
            </div>
        @endif

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

@if(count($printHtmlEntries) > 0)
    @foreach($printHtmlEntries as $htmlEntry)
        <div class="template-entry">
            {!! $htmlEntry !!}
        </div>
    @endforeach
@else
    <table>
        <thead>
        <tr>
            @foreach($printColumns as $column)
                <th>{{ $column['label'] ?? $column['key'] ?? 'Поле' }}</th>
            @endforeach
        </tr>
        </thead>

        <tbody>
        @forelse($entries as $index => $entry)
            <tr>
                @foreach($printColumns as $column)
                    @php
                        $columnType = $column['type'] ?? 'field';
                        $columnKey = $column['key'] ?? null;
                        $displayValue = '—';

                        if ($columnType === 'system') {
                            if ($columnKey === 'number') {
                                $displayValue = $index + 1;
                            } elseif ($columnKey === 'entry_date') {
                                $displayValue = $entry->entry_date ? $entry->entry_date->format('d.m.Y') : '—';
                            } elseif ($columnKey === 'created_by') {
                                $displayValue = $entry->user->name ?? '—';
                            } elseif ($columnKey === 'division') {
                                $displayValue = $entry->division->name ?? '—';
                            } elseif ($columnKey === 'status') {
                                $displayValue = $entry->status === 'approved'
                                    ? 'Подтверждено'
                                    : ($entry->status === 'rejected' ? 'Отклонено' : 'На проверке');
                            } elseif ($columnKey === 'checked_by') {
                                $displayValue = $entry->checker->name ?? '—';

                                if ($entry->checked_at && $displayValue !== '—') {
                                    $displayValue .= ' / ' . $entry->checked_at->format('d.m.Y H:i');
                                }
                            } elseif ($columnKey === 'last_comment') {
                                $displayValue = $entry->lastComment ? $entry->lastComment->comment : '—';
                            }
                        } else {
                            $field = collect($schema)->firstWhere('key', $columnKey) ?? [];
                            $type = $field['type'] ?? 'string';
                            $value = $columnKey && is_array($entry->data) ? ($entry->data[$columnKey] ?? null) : null;

                            if ($value === null || $value === '') {
                                $displayValue = '—';
                            } elseif ($type === 'directory') {
                                $list = $directoryValues[$field['directory_id'] ?? 0] ?? collect();
                                $directoryItem = $list->firstWhere('id', (int)$value);
                                $displayField = $field['directory_display_field'] ?? null;
                                $displayValue = $directoryItem
                                    ? (($displayField && !empty($directoryItem->data[$displayField])) ? $directoryItem->data[$displayField] : $directoryItem->value)
                                    : $value;
                            } else {
                                $displayValue = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
                            }
                        }
                    @endphp

                    <td>{{ $displayValue }}</td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ max(1, count($printColumns)) }}" style="text-align:center;">
                    Записи не найдены
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
@endif

@if($printSettings['show_signatures'] ?? true)
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
@endif

<script>
    window.addEventListener('load', function () {
        setTimeout(function () {
            window.print();
        }, 300);
    });
</script>

</body>
</html>
