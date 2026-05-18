<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    protected $fillable = [
        'journal_template_id',
        'division_id',
        'user_id',
        'entry_date',
        'data',
        'status',
        'checked_by',
        'checked_at',
    ];

    protected $casts = [
        'data' => 'array',
        'entry_date' => 'date',
        'checked_at' => 'datetime',
    ];

    public function template()
    {
        return $this->belongsTo(JournalTemplate::class, 'journal_template_id');
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function checker()
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function comments()
    {
        return $this->hasMany(JournalEntryComment::class, 'journal_entry_id');
    }

    public function rootComments()
    {
        return $this->hasMany(JournalEntryComment::class, 'journal_entry_id')
            ->whereNull('parent_id')
            ->with(['user', 'editor', 'replies'])
            ->orderBy('created_at');
    }

    public function lastComment()
    {
        return $this->hasOne(JournalEntryComment::class, 'journal_entry_id')
            ->latestOfMany();
    }

    public function logs()
    {
        return $this->hasMany(JournalEntryLog::class, 'journal_entry_id')
            ->with('user')
            ->orderByDesc('id');
    }

}
