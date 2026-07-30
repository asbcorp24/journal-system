<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserFavorite extends Model
{
    public const TYPE_JOURNAL = 'journal';
    public const TYPE_DIRECTORY = 'directory';

    protected $fillable = [
        'user_id',
        'entity_type',
        'entity_id',
        'sort_order',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
