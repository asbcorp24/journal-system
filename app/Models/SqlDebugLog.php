<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SqlDebugLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'connection_name',
        'driver',
        'sql',
        'bindings',
        'time_ms',
        'request_path',
        'user_id',
        'created_at',
    ];

    protected $casts = [
        'bindings' => 'array',
        'created_at' => 'datetime',
        'time_ms' => 'float',
    ];
}
