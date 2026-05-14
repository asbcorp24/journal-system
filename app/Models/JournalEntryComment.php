<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntryComment extends Model
{
    protected $fillable = [
        'journal_entry_id',
        'user_id',
        'comment',
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
