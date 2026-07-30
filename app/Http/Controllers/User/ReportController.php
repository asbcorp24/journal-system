<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\DirectoryValue;
use App\Models\Division;
use App\Models\JournalTemplate;
use App\Models\ReportTemplate;
use App\Models\User;
use App\Models\UserReportPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller
{
    public function index()
    {
        $reports = ReportTemplate::query()
            ->where('is_active', true)
            ->when(!$this->currentUserHasFullReportAccess(), function ($query) {
                $query->whereIn('id', $this->currentUserReportIds());
            })
            ->orderBy('name')
            ->get();

        return view('user.reports.index', compact('reports'));
    }

    public function show(ReportTemplate $report)
    {
        $this->ensureReportAccess($report);

        $sources = $this->getSourcesForReport($report);

        return response()->json([
            'success' => true,
            'report' => $report,
            'sources' => $sources,
        ]);
    }

    public function run(Request $request, ReportTemplate $report)
    {
        $this->ensureReportAccess($report);

        $bindings = $this->validateAndBuildBindings($request, $report);
        $rows = $this->executeReport($report, $bindings);

        return response()->json([
            'success' => true,
            'columns' => $this->getColumns($rows),
            'rows' => $rows,
        ]);
    }

    public function export(Request $request, ReportTemplate $report)
    {
        $this->ensureReportAccess($report);

        $bindings = $this->validateAndBuildBindings($request, $report);
        $rows = $this->executeReport($report, $bindings);
        $columns = $this->getColumns($rows);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

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

    public function print(Request $request, ReportTemplate $report)
    {
        $this->ensureReportAccess($report);

        $bindings = $this->validateAndBuildBindings($request, $report);
        $rows = $this->executeReport($report, $bindings);
        $columns = $this->getColumns($rows);
        $printSettings = $report->print_settings ?? [];

        return view('user.reports.print', [
            'report' => $report,
            'columns' => $columns,
            'rows' => $rows,
            'params' => $request->input('params', []),
            'printedAt' => now(),
            'renderedHtml' => $this->renderReportPrintHtml($report, $columns, $rows, $request->input('params', [])),
            'printOrientation' => $printSettings['orientation'] ?? 'portrait',
            'printTitle' => trim((string) ($printSettings['title'] ?? '')) ?: $report->name,
        ]);
    }

    private function executeReport(ReportTemplate $report, array $bindings): array
    {
        $rows = DB::select($report->sql_query, $bindings);

        return $this->normalizeReportRows($rows);
    }

    private function currentUserHasFullReportAccess(): bool
    {
        return session('user_role') === 'admin';
    }

    private function currentUserReportIds(): array
    {
        $userId = (int) session('user_id');

        if (!$userId) {
            return [];
        }

        return UserReportPermission::query()
            ->where('user_id', $userId)
            ->pluck('report_template_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function ensureReportAccess(ReportTemplate $report): void
    {
        if (!$report->is_active) {
            abort(404);
        }

        if ($this->currentUserHasFullReportAccess()) {
            return;
        }

        $hasAccess = UserReportPermission::query()
            ->where('user_id', session('user_id'))
            ->where('report_template_id', $report->id)
            ->exists();

        if (!$hasAccess) {
            abort(403, 'Нет доступа к этому отчёту');
        }
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

                $bindings[$key] = (int) $value;
                continue;
            }

            $bindings[$key] = $value;
        }

        $sql = $report->sql_query;

        if (str_contains($sql, ':current_division_id')) {
            $bindings['current_division_id'] = session('user_division_id');
        }

        if (str_contains($sql, ':current_user_id')) {
            $bindings['current_user_id'] = session('user_id');
        }

        return $this->filterBindingsBySql($sql, $bindings);
    }

    private function normalizeReportRows(array $rows): array
    {
        $normalizedRows = [];

        foreach ($rows as $row) {
            $row = (array) $row;
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
            return (array) $value;
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

        return (string) $value;
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

    private function renderReportPrintHtml(ReportTemplate $report, array $columns, array $rows, array $params): ?string
    {
        $template = trim((string) data_get($report->print_settings, 'body_html', ''));

        if ($template === '') {
            return null;
        }

        $template = $this->sanitizePrintTemplateHtml($template);

        if (preg_match('/\{\{#rows\}\}(.*?)\{\{\/rows\}\}/s', $template)) {
            $globalValues = $this->reportGlobalTemplateValues($report, $columns, $rows, $params);

            $template = preg_replace_callback('/\{\{#rows\}\}(.*?)\{\{\/rows\}\}/s', function ($matches) use ($rows, $globalValues) {
                $rowTemplate = $matches[1] ?? '';
                $html = '';

                foreach ($rows as $index => $row) {
                    $html .= $this->replaceReportPrintTokens(
                        $rowTemplate,
                        array_merge($globalValues, $this->reportRowTemplateValues($row, $index + 1))
                    );
                }

                return $html;
            }, $template);
        }

        return $this->replaceReportPrintTokens(
            $template,
            $this->reportGlobalTemplateValues($report, $columns, $rows, $params),
            $this->buildReportPrintTableHtml($columns, $rows)
        );
    }

    private function replaceReportPrintTokens(string $template, array $values, ?string $tableHtml = null): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', function ($matches) use ($values, $tableHtml) {
            $key = $matches[1] ?? '';

            if ($key === 'table') {
                return $tableHtml ?? '';
            }

            return e($values[$key] ?? '');
        }, $template);
    }

    private function reportGlobalTemplateValues(ReportTemplate $report, array $columns, array $rows, array $params): array
    {
        $values = [
            'report.name' => $report->name,
            'report.description' => $report->description ?? '',
            'report.row_count' => count($rows),
            'report.columns_count' => count($columns),
            'print.date' => now()->format('d.m.Y H:i'),
            'print.title' => trim((string) data_get($report->print_settings, 'title', '')) ?: $report->name,
        ];

        foreach ($params as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            $values['params.' . $key] = (string) ($value ?? '');
        }

        return $values;
    }

    private function reportRowTemplateValues(array $row, int $index): array
    {
        $values = [
            'row.index' => $index,
        ];

        foreach ($row as $column => $value) {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            $values['row.' . $column] = (string) ($value ?? '');
        }

        return $values;
    }

    private function buildReportPrintTableHtml(array $columns, array $rows): string
    {
        $html = '<table><thead><tr>';

        foreach ($columns as $column) {
            $html .= '<th>' . e($column) . '</th>';
        }

        $html .= '</tr></thead><tbody>';

        if (count($rows) === 0) {
            $html .= '<tr><td colspan="' . max(1, count($columns)) . '" style="text-align:center;">Данных нет</td></tr>';
        }

        foreach ($rows as $row) {
            $html .= '<tr>';

            foreach ($columns as $column) {
                $html .= '<td>' . e((string) ($row[$column] ?? '')) . '</td>';
            }

            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    private function sanitizePrintTemplateHtml(string $html): string
    {
        $allowedTags = '<div><section><article><header><footer><main><p><br><span><strong><b><em><i><u><small><h1><h2><h3><h4><h5><h6><table><thead><tbody><tfoot><tr><th><td><ul><ol><li><hr>';
        $html = strip_tags($html, $allowedTags);
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = preg_replace('/\s(?:href|src)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = preg_replace('/javascript\s*:/i', '', $html);

        return $html;
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
                $sources[$key] = Division::orderBy('name')->get(['id', 'name']);
            } elseif (($field['source'] ?? '') === 'users') {
                $sources[$key] = User::orderBy('name')->get(['id', 'name']);
            } elseif (($field['source'] ?? '') === 'journal_templates') {
                $sources[$key] = JournalTemplate::orderBy('name')->get(['id', 'name']);
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
