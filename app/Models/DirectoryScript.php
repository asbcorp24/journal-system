<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DirectoryScript extends Model
{
    protected $fillable = [
        'directory_id',
        'name',
        'description',
        'code',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function directory()
    {
        return $this->belongsTo(Directory::class);
    }
}
