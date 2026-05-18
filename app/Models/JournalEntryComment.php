<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntryComment extends Model
{
    protected $fillable = [
        'journal_entry_id',
        'parent_id',
        'user_id',
        'comment',
        'edited_at',
        'edited_by',
    ];

    protected $casts = [
        'edited_at' => 'datetime',
    ];

    public function entry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    public function parent()
    {
        return $this->belongsTo(JournalEntryComment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(JournalEntryComment::class, 'parent_id')
            ->with(['user', 'editor', 'replies'])
            ->orderBy('created_at');
    }
}
