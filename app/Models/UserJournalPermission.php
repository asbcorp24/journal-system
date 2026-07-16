<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserJournalPermission extends Model
{
    public const ACCESS_VIEW = 'view';
    public const ACCESS_FULL = 'full';

    protected $fillable = [
        'user_id',
        'division_id',
        'journal_template_id',
        'access_level',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function journalTemplate()
    {
        return $this->belongsTo(JournalTemplate::class, 'journal_template_id');
    }
}
