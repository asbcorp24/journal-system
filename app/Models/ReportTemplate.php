<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportTemplate extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'sql_query',
        'params_schema',
        'is_active',
    ];

    protected $casts = [
        'params_schema' => 'array',
        'is_active' => 'boolean',
    ];
}
