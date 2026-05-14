<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Division extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function directories()
    {
        return $this->belongsToMany(Directory::class, 'directory_division');
    }

    public function journalTemplates()
    {
        return $this->belongsToMany(JournalTemplate::class, 'journal_template_division');
    }

    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class);
    }
}
