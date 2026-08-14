<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalTemplate extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'schema',
        'is_active',
        'created_by',
        'approver_user_id',
    ];

    protected $casts = [
        'schema' => 'array',
        'is_active' => 'boolean',
    ];

    public function divisions()
    {
        return $this->belongsToMany(Division::class, 'journal_template_division');
    }

    public function entries()
    {
        return $this->hasMany(JournalEntry::class, 'journal_template_id');
    }

    public function printTemplates()
    {
        return $this->hasMany(JournalPrintTemplate::class, 'journal_template_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
