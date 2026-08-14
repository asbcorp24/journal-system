<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('journal_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('journal_templates', 'approver_user_id')) {
                $table->foreignId('approver_user_id')
                    ->nullable()
                    ->after('created_by')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down()
    {
        Schema::table('journal_templates', function (Blueprint $table) {
            if (Schema::hasColumn('journal_templates', 'approver_user_id')) {
                $table->dropConstrainedForeignId('approver_user_id');
            }
        });
    }
};
