<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Печать справочника - {{ $directory->name }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #000; background: #fff; font-size: 12px; margin: 20px; }
        h1 { margin: 0 0 6px; font-size: 22px; }
        .meta { margin-bottom: 16px; color: #333; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 6px 8px; vertical-align: top; }
        th { background: #eee; text-align: left; }
        .no-print { margin-bottom: 16px; }
        .print-btn { padding: 8px 14px; border: 1px solid #000; background: #fff; cursor: pointer; }
        @media print { .no-print { display: none; } body { margin: 8mm; } }
    </style>
</head>
<body>
<div class="no-print">
    <button class="print-btn" onclick="window.print()">Печать</button>
</div>

<h1>{{ $directory->name }}</h1>
<div class="meta">
    {{ $directory->description ?: 'Справочник' }}<br>
    Сформировано: {{ now()->format('d.m.Y H:i') }}
</div>

<table>
    <thead>
    <tr>
        <th style="width: 50px;">ID</th>
        @if(empty($schema))
            <th>Значение</th>
        @else
            @foreach($schema as $field)
                <th>{{ $field['label'] ?? $field['key'] ?? 'Поле' }}</th>
            @endforeach
        @endif
        <th style="width: 120px;">Код</th>
        <th style="width: 90px;">Активно</th>
    </tr>
    </thead>
    <tbody>
    @foreach($values as $value)
        <tr>
            <td>{{ $value->id }}</td>
            @if(empty($schema))
                <td>{{ $value->value }}</td>
            @else
                @foreach($schema as $field)
                    @php
                        $key = $field['key'] ?? null;
                        $data = is_array($value->data) ? $value->data : [];
                    @endphp
                    <td>{{ $key ? ($data[$key] ?? '-') : '-' }}</td>
                @endforeach
            @endif
            <td>{{ $value->code ?: '-' }}</td>
            <td>{{ $value->is_active ? 'Да' : 'Нет' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
