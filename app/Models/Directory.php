<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Directory extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'schema',
        'created_by',
    ];

    protected $casts = [
        'schema' => 'array',
    ];

    public function values()
    {
        return $this->hasMany(DirectoryValue::class);
    }

    public function scripts()
    {
        return $this->hasMany(DirectoryScript::class)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function divisions()
    {
        return $this->belongsToMany(Division::class, 'directory_division');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
