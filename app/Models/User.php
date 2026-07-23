<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'division_id',
        'is_active',
        'can_edit_directory_templates',
        'can_edit_journal_templates',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'can_edit_directory_templates' => 'boolean',
        'can_edit_journal_templates' => 'boolean',
    ];

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function unreadNotifications()
    {
        return $this->hasMany(Notification::class)
            ->where('is_read', false);
    }

    public function journalPermissions()
    {
        return $this->hasMany(UserJournalPermission::class);
    }
}
