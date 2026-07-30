<?php

namespace Database\Seeders;

use App\Models\Directory;
use App\Models\DirectoryValue;
use App\Models\Division;
use App\Models\JournalTemplate;
use App\Models\ReportTemplate;
use Illuminate\Database\Seeder;

class WarehouseTemplatesSeeder extends Seeder
{
    public function run()
    {
        $divisionIds = Division::query()
            ->pluck('id')
            ->unique()
            ->values()
            ->all();

        $nomenclature = $this->upsertDirectory(
            'Номенклатура склада',
            'warehouse_nomenclature',
            'Номенклатура с номером, названием, параметрами и единицей измерения',
            [
                [
                    'label' => 'Наименование',
                    'key' => 'name',
                    'type' => 'text',
                    'tab' => 'Основное',
                    'required' => true,
                    'unique' => false,
                ],
                [
                    'label' => 'Номенклатурный номер',
                    'key' => 'item_number',
                    'type' => 'text',
                    'tab' => 'Основное',
                    'required' => true,
                    'unique' => true,
                ],
                [
                    'label' => 'Параметры',
                    'key' => 'parameters',
                    'type' => 'text',
                    'tab' => 'Основное',
                    'required' => false,
                    'unique' => false,
                ],
                [
                    'label' => 'Единица измерения',
                    'key' => 'unit',
                    'type' => 'text',
                    'tab' => 'Основное',
                    'required' => true,
                    'unique' => false,
                ],
            ],
            $divisionIds
        );

        $sources = $this->upsertDirectory(
            'Источники поступления',
            'warehouse_sources',
            'Поставщики, возвраты и внутренние источники поступления',
            [
                [
                    'label' => 'Наименование',
                    'key' => 'name',
                    'type' => 'text',
                    'tab' => 'Основное',
                    'required' => true,
                    'unique' => false,
                ],
                [
                    'label' => 'Номер источника',
                    'key' => 'source_number',
                    'type' => 'text',
                    'tab' => 'Основное',
                    'required' => true,
                    'unique' => true,
                ],
                [
                    'label' => 'Комментарий',
                    'key' => 'comment',
                    'type' => 'text',
                    'tab' => 'Основное',
                    'required' => false,
                    'unique' => false,
                ],
            ],
            $divisionIds
        );

        $workshops = $this->upsertDirectory(
            'Цеха для выдачи',
            'warehouse_workshops',
            'Цеха и участки, куда выдаётся номенклатура со склада',
            [
                [
                    'label' => 'Наименование цеха',
                    'key' => 'name',
                    'type' => 'text',
                    'tab' => 'Основное',
                    'required' => true,
                    'unique' => false,
                ],
                [
                    'label' => 'Номер цеха',
                    'key' => 'shop_number',
                    'type' => 'text',
                    'tab' => 'Основное',
                    'required' => true,
                    'unique' => true,
                ],
            ],
            $divisionIds
        );

        $this->upsertDirectoryValue($nomenclature, [
            'name' => 'Подшипник 6205',
            'item_number' => 'N-100',
            'parameters' => '25x52x15',
            'unit' => 'шт',
        ]);
        $this->upsertDirectoryValue($nomenclature, [
            'name' => 'Болт М10х40',
            'item_number' => 'N-200',
            'parameters' => 'оцинкованный',
            'unit' => 'шт',
        ]);
        $this->upsertDirectoryValue($sources, [
            'name' => 'Поставщик А',
            'source_number' => 'SRC-001',
            'comment' => 'Основной поставщик',
        ]);
        $this->upsertDirectoryValue($sources, [
            'name' => 'Внутреннее перемещение',
            'source_number' => 'SRC-002',
            'comment' => 'Со склада другого участка',
        ]);
        $this->upsertDirectoryValue($workshops, [
            'name' => 'Механообработка',
            'shop_number' => 'SHOP-01',
        ]);
        $this->upsertDirectoryValue($workshops, [
            'name' => 'Сборочный цех',
            'shop_number' => 'SHOP-02',
        ]);

        $this->upsertJournal(
            'Получение номенклатуры на склад',
            'warehouse_receipt',
            'Приход номенклатуры на склад от поставщика или внутреннего источника',
            [
                [
                    'key' => 'receipt_number',
                    'label' => 'Номер документа',
                    'type' => 'string',
                    'tab' => 'Основное',
                    'required' => true,
                    'filterable' => true,
                ],
                [
                    'key' => 'operation_date',
                    'label' => 'Дата',
                    'type' => 'date',
                    'tab' => 'Основное',
                    'required' => true,
                    'filterable' => true,
                ],
                [
                    'key' => 'item',
                    'label' => 'Номенклатура',
                    'type' => 'directory',
                    'tab' => 'Основное',
                    'required' => true,
                    'filterable' => true,
                    'directory_id' => $nomenclature->id,
                    'directory_display_field' => 'name',
                ],
                [
                    'key' => 'quantity',
                    'label' => 'Количество',
                    'type' => 'number',
                    'tab' => 'Основное',
                    'required' => true,
                    'filterable' => false,
                    'validation' => [
                        'min' => 0,
                    ],
                ],
                [
                    'key' => 'source',
                    'label' => 'Откуда пришло',
                    'type' => 'directory',
                    'tab' => 'Основное',
                    'required' => true,
                    'filterable' => true,
                    'directory_id' => $sources->id,
                    'directory_display_field' => 'name',
                ],
            ],
            $divisionIds
        );

        $this->upsertJournal(
            'Выдача номенклатуры со склада',
            'warehouse_issue',
            'Выдача номенклатуры со склада в подразделения и цеха',
            [
                [
                    'key' => 'item',
                    'label' => 'Номенклатура',
                    'type' => 'directory',
                    'tab' => 'Основное',
                    'required' => true,
                    'filterable' => true,
                    'directory_id' => $nomenclature->id,
                    'directory_display_field' => 'name',
                ],
                [
                    'key' => 'item_number',
                    'label' => 'Номер номенклатуры',
                    'type' => 'sql',
                    'tab' => 'Основное',
                    'required' => true,
                    'filterable' => true,
                    'sql_query' => "SELECT json_extract(data, '$.item_number') FROM directory_values WHERE id = :item",
                ],
                [
                    'key' => 'unit',
                    'label' => 'Единица измерения',
                    'type' => 'sql',
                    'tab' => 'Основное',
                    'required' => true,
                    'filterable' => true,
                    'sql_query' => "SELECT json_extract(data, '$.unit') FROM directory_values WHERE id = :item",
                ],
                [
                    'key' => 'operation_date',
                    'label' => 'Дата',
                    'type' => 'date',
                    'tab' => 'Основное',
                    'required' => true,
                    'filterable' => true,
                ],
                [
                    'key' => 'quantity',
                    'label' => 'Сколько отпущено',
                    'type' => 'number',
                    'tab' => 'Основное',
                    'required' => true,
                    'filterable' => false,
                    'validation' => [
                        'min' => 0,
                    ],
                ],
                [
                    'key' => 'workshop',
                    'label' => 'В какой цех',
                    'type' => 'directory',
                    'tab' => 'Основное',
                    'required' => true,
                    'filterable' => true,
                    'directory_id' => $workshops->id,
                    'directory_display_field' => 'name',
                ],
                [
                    'key' => 'stock_balance',
                    'label' => 'Сколько осталось на складе',
                    'type' => 'sql',
                    'tab' => 'Основное',
                    'required' => true,
                    'filterable' => false,
                    'sql_query' => "SELECT ROUND(
COALESCE((
    SELECT SUM(CAST(json_extract(data, '$.quantity') AS REAL))
    FROM journal_entries
    WHERE journal_template_id = (SELECT id FROM journal_templates WHERE code = 'warehouse_receipt' LIMIT 1)
      AND deleted_at IS NULL
      AND status != 'rejected'
      AND CAST(json_extract(data, '$.item') AS INTEGER) = CAST(:item AS INTEGER)
), 0)
-
COALESCE((
    SELECT SUM(CAST(json_extract(data, '$.quantity') AS REAL))
    FROM journal_entries
    WHERE journal_template_id = (SELECT id FROM journal_templates WHERE code = 'warehouse_issue' LIMIT 1)
      AND deleted_at IS NULL
      AND status != 'rejected'
      AND CAST(json_extract(data, '$.item') AS INTEGER) = CAST(:item AS INTEGER)
      AND (:entry_id IS NULL OR id != :entry_id)
), 0)
- COALESCE(CAST(:quantity AS REAL), 0), 3)",
                ],
            ],
            $divisionIds
        );

        $this->upsertReport(
            'Остатки номенклатуры на складе',
            'warehouse_stock_balance',
            'Показывает по каждой позиции номенклатуры приход, расход и текущий остаток на складе',
            <<<'SQL'
SELECT
    dv.id AS item_id,
    dv.value AS item_name,
    json_extract(dv.data, '$.item_number') AS item_number,
    json_extract(dv.data, '$.unit') AS unit,
    json_extract(dv.data, '$.parameters') AS parameters,
    ROUND(COALESCE(receipts.received_quantity, 0), 3) AS received_quantity,
    ROUND(COALESCE(issues.issued_quantity, 0), 3) AS issued_quantity,
    ROUND(COALESCE(receipts.received_quantity, 0) - COALESCE(issues.issued_quantity, 0), 3) AS stock_balance
FROM directory_values dv
INNER JOIN directories d
    ON d.id = dv.directory_id
LEFT JOIN (
    SELECT
        CAST(json_extract(je.data, '$.item') AS INTEGER) AS item_id,
        SUM(CAST(json_extract(je.data, '$.quantity') AS REAL)) AS received_quantity
    FROM journal_entries je
    WHERE je.journal_template_id = (
        SELECT id
        FROM journal_templates
        WHERE code = 'warehouse_receipt'
           OR code LIKE 'warehouse_receipt_import%'
        ORDER BY CASE WHEN code = 'warehouse_receipt' THEN 0 ELSE 1 END, id
        LIMIT 1
    )
      AND je.deleted_at IS NULL
      AND je.status != 'rejected'
    GROUP BY CAST(json_extract(je.data, '$.item') AS INTEGER)
) receipts
    ON receipts.item_id = dv.id
LEFT JOIN (
    SELECT
        CAST(json_extract(je.data, '$.item') AS INTEGER) AS item_id,
        SUM(CAST(json_extract(je.data, '$.quantity') AS REAL)) AS issued_quantity
    FROM journal_entries je
    WHERE je.journal_template_id = (
        SELECT id
        FROM journal_templates
        WHERE code = 'warehouse_issue'
           OR code LIKE 'warehouse_issue_import%'
        ORDER BY CASE WHEN code = 'warehouse_issue' THEN 0 ELSE 1 END, id
        LIMIT 1
    )
      AND je.deleted_at IS NULL
      AND je.status != 'rejected'
    GROUP BY CAST(json_extract(je.data, '$.item') AS INTEGER)
) issues
    ON issues.item_id = dv.id
WHERE d.code = 'warehouse_nomenclature'
  AND dv.is_active = 1
ORDER BY item_name
SQL,
            []
        );
    }

    private function upsertDirectory(string $name, string $code, string $description, array $schema, array $divisionIds): Directory
    {
        $directory = Directory::query()->firstOrNew([
            'code' => $code,
        ]);

        $directory->fill([
            'name' => $name,
            'code' => $code,
            'description' => $description,
            'schema' => $schema,
            'created_by' => null,
        ]);
        $directory->save();
        $directory->divisions()->syncWithoutDetaching($divisionIds);

        return $directory;
    }

    private function upsertDirectoryValue(Directory $directory, array $data): void
    {
        $displayValue = (string) ($data['name'] ?? reset($data) ?? 'Запись');
        $code = (string) ($data['item_number'] ?? $data['source_number'] ?? $data['shop_number'] ?? '');

        DirectoryValue::query()->updateOrCreate(
            [
                'directory_id' => $directory->id,
                'value' => $displayValue,
            ],
            [
                'data' => $data,
                'code' => $code !== '' ? $code : null,
                'sort_order' => 0,
                'is_active' => true,
            ]
        );
    }

    private function upsertJournal(string $name, string $code, string $description, array $schema, array $divisionIds): JournalTemplate
    {
        $template = JournalTemplate::query()->firstOrNew([
            'code' => $code,
        ]);

        $template->fill([
            'name' => $name,
            'code' => $code,
            'description' => $description,
            'schema' => $schema,
            'is_active' => true,
            'created_by' => null,
        ]);
        $template->save();
        $template->divisions()->syncWithoutDetaching($divisionIds);

        return $template;
    }

    private function upsertReport(string $name, string $code, string $description, string $sqlQuery, array $paramsSchema): ReportTemplate
    {
        $report = ReportTemplate::query()->firstOrNew([
            'code' => $code,
        ]);

        $report->fill([
            'name' => $name,
            'code' => $code,
            'description' => $description,
            'sql_query' => trim($sqlQuery),
            'params_schema' => $paramsSchema,
            'is_active' => true,
        ]);
        $report->save();

        return $report;
    }
}
