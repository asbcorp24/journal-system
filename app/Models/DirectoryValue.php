<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DirectoryValue extends Model
{
    protected $fillable = [
        'directory_id',
        'value',
        'data',
        'code',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'data' => 'array',
        'is_active' => 'boolean',
    ];

    public function directory()
    {
        return $this->belongsTo(Directory::class);
    }
}
