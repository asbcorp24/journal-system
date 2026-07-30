<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserReportPermission extends Model
{
    protected $fillable = [
        'user_id',
        'report_template_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reportTemplate()
    {
        return $this->belongsTo(ReportTemplate::class, 'report_template_id');
    }
}
