<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalPrintTemplate extends Model
{
    protected $fillable = [
        'journal_template_id',
        'name',
        'title',
        'description',
        'settings',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    public function journalTemplate()
    {
        return $this->belongsTo(JournalTemplate::class, 'journal_template_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
