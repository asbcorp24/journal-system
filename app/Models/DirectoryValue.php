<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DirectoryValue extends Model
{
    protected $fillable = [
        'directory_id',
        'value',
        'code',
        'sort_order',
        'is_active',
    ];

    public function directory()
    {
        return $this->belongsTo(Directory::class);
    }
}
