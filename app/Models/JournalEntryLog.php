<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntryLog extends Model
{
    protected $fillable = [
        'journal_entry_id',
        'user_id',
        'action',
        'old_status',
        'new_status',
        'old_data',
        'new_data',
        'comment',
        'ip_address',
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
    ];

    public function entry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
