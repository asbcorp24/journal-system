<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedFilter extends Model
{
    public const ENTITY_JOURNAL = 'journal';
    public const ENTITY_DIRECTORY = 'directory';

    protected $fillable = [
        'user_id',
        'entity_type',
        'entity_id',
        'name',
        'description',
        'visible_fields',
        'values',
    ];

    protected $casts = [
        'visible_fields' => 'array',
        'values' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
