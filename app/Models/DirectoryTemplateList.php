<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DirectoryTemplateList extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'items',
        'created_by',
    ];

    protected $casts = [
        'items' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
