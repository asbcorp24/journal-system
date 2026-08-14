<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DirectoryValue extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'directory_id',
        'created_by',
        'updated_by',
        'deleted_by',
        'value',
        'data',
        'code',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'data' => 'array',
        'is_active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function directory()
    {
        return $this->belongsTo(Directory::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
