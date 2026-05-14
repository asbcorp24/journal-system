<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Directory extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
    ];

    public function values()
    {
        return $this->hasMany(DirectoryValue::class);
    }

    public function divisions()
    {
        return $this->belongsToMany(Division::class, 'directory_division');
    }
}
