<?php

namespace Database\Seeders;

use App\Models\Division;
use Illuminate\Database\Seeder;

class DivisionSeeder extends Seeder
{
    public function run()
    {
        $items = [
            'Цех №1',
            'Цех №2',
            'Лаборатория',
            'Склад',
            'Производственный участок',
        ];

        foreach ($items as $item) {
            Division::firstOrCreate([
                'name' => $item,
            ]);
        }
    }
}
