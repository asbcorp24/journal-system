<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\DirectoryValue;
use App\Models\Division;
use App\Models\JournalTemplate;
use App\Models\ReportTemplate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller
{
    public function index()
    {
        if (session('user_role') !== 'admin') {
            abort(403, 'Отчёты доступны только администраторам');
        }

        $reports = ReportTemplate::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('user.reports.index', compact('reports'));
    }

    public function show(ReportTemplate $report)
    {
        if (session('user_role') !== 'admin') {
            abort(403, 'Отчёты доступны только администраторам');
        }

        if (!$report->is_active) {
            abort(404);
        }

        $sources = $this->getSourcesForReport($report);

        return response()->json([
            'success' => true,
            'report' => $report,
            'sources' => $sources,
        ]);
    }

    public function run(Request $request, ReportTemplate $report)
    {
        if (session('user_role') !== 'admin') {
            abort(403, 'Отчёты доступны только администраторам');
        }

        if (!$report->is_active) {
            abort(404);
        }

        $bindings = $this->validateAndBuildBindings($request, $report);

        $rows = DB::select($report->sql_query, $bindings);

        $rows = $this->normalizeReportRows($rows);

        return response()->json([
            'success' => true,
            'columns' => $this->getColumns($rows),
            'rows' => $rows,
        ]);
    }

    public function export(Request $request, ReportTemplate $report)
    {
        if (session('user_role') !== 'admin') {
            abort(403, 'Отчёты доступны только администраторам');
        }

        if (!$report->is_active) {
            abort(404);
        }

        $bindings = $this->validateAndBuildBindings($request, $report);

        $rows = DB::select($report->sql_query, $bindings);

        $rows = $this->normalizeReportRows($rows);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $columns = $this->getColumns($rows);

        $colIndex = 1;

        foreach ($columns as $column) {
            $sheet->setCellValueByColumnAndRow($colIndex, 1, $column);
            $colIndex++;
        }

        $rowIndex = 2;

        foreach ($rows as $row) {
            $colIndex = 1;

            foreach ($columns as $column) {
                $value = $row[$column] ?? '';

                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                }

                $sheet->setCellValueByColumnAndRow($colIndex, $rowIndex, $value);
                $colIndex++;
            }

            $rowIndex++;
        }

        foreach (range(1, max(1, count($columns))) as $columnIndex) {
            $sheet->getColumnDimensionByColumn($columnIndex)->setAutoSize(true);
        }

        $fileName = 'report_' . ($report->code ?: $report->id) . '_' . date('Ymd_His') . '.xlsx';

        $tempPath = storage_path('app/' . $fileName);

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }

    private function validateAndBuildBindings(Request $request, ReportTemplate $report): array
    {
        $schema = $report->params_schema ?? [];
        $input = $request->input('params', []);

        $bindings = [];

        foreach ($schema as $field) {
            $key = $field['key'] ?? null;
            $label = $field['label'] ?? $key;
            $type = $field['type'] ?? 'string';
            $required = !empty($field['required']);

            if (!$key) {
                continue;
            }

            $value = $input[$key] ?? null;

            if ($required && ($value === null || $value === '')) {
                abort(response()->json([
                    'success' => false,
                    'message' => "Параметр «{$label}» обязателен",
                ], 422));
            }

            if ($value === null || $value === '') {
                $bindings[$key] = null;
                continue;
            }

            if ($type === 'number' || $type === 'directory') {
                if (!is_numeric($value)) {
                    abort(response()->json([
                        'success' => false,
                        'message' => "Параметр «{$label}» должен быть числом",
                    ], 422));
                }

                $bindings[$key] = (int)$value;
                continue;
            }

            $bindings[$key] = $value;
        }

        /*
         * Системные параметры добавляем только если они реально есть в SQL.
         */
        $sql = $report->sql_query;

        if (str_contains($sql, ':current_division_id')) {
            $bindings['current_division_id'] = session('user_division_id');
        }

        if (str_contains($sql, ':current_user_id')) {
            $bindings['current_user_id'] = session('user_id');
        }

        /*
         * Финальная защита:
         * оставляем только те bindings, которые реально есть в SQL.
         */
        $bindings = $this->filterBindingsBySql($sql, $bindings);

        return $bindings;
    }
    private function normalizeReportRows(array $rows): array
    {
        $normalizedRows = [];

        foreach ($rows as $row) {
            $row = (array)$row;

            $normalized = [];

            foreach ($row as $column => $value) {
                if ($column === 'data') {
                    $jsonData = $this->decodeJsonData($value);

                    foreach ($jsonData as $jsonKey => $jsonValue) {
                        $normalized['data_' . $jsonKey] = $this->formatJsonCellValue($jsonValue);
                    }

                    continue;
                }

                $normalized[$column] = $this->formatJsonCellValue($value);
            }

            $normalizedRows[] = $normalized;
        }

        return $normalizedRows;
    }

    private function decodeJsonData($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return (array)$value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    private function formatJsonCellValue($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Да' : 'Нет';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string)$value;
    }

    private function getColumns(array $rows): array
    {
        $columns = [];

        foreach ($rows as $row) {
            foreach (array_keys($row) as $column) {
                if (!in_array($column, $columns, true)) {
                    $columns[] = $column;
                }
            }
        }

        return $columns;
    }
    private function filterBindingsBySql(string $sql, array $bindings): array
    {
        preg_match_all('/:[a-zA-Z_][a-zA-Z0-9_]*/', $sql, $matches);

        $placeholders = collect($matches[0] ?? [])
            ->map(function ($item) {
                return ltrim($item, ':');
            })
            ->unique()
            ->values()
            ->toArray();

        return array_intersect_key($bindings, array_flip($placeholders));
    }


    private function getSourcesForReport(ReportTemplate $report): array
    {
        $schema = $report->params_schema ?? [];
        $sources = [];

        foreach ($schema as $field) {
            $key = $field['key'] ?? null;

            if (!$key) {
                continue;
            }

            if (($field['source'] ?? '') === 'divisions') {
                $sources[$key] = Division::orderBy('name')
                    ->get(['id', 'name']);
            } elseif (($field['source'] ?? '') === 'users') {
                $sources[$key] = User::orderBy('name')
                    ->get(['id', 'name']);
            } elseif (($field['source'] ?? '') === 'journal_templates') {
                $sources[$key] = JournalTemplate::orderBy('name')
                    ->get(['id', 'name']);
            } elseif (!empty($field['directory_id'])) {
                $sources[$key] = DirectoryValue::where('directory_id', $field['directory_id'])
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('value')
                    ->get([
                        'id',
                        DB::raw('value as name'),
                    ]);
            }
        }

        return $sources;
    }
}
