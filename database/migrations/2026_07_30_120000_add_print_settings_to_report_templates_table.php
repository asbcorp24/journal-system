<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('report_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('report_templates', 'print_settings')) {
                $table->json('print_settings')->nullable()->after('params_schema');
            }
        });
    }

    public function down()
    {
        Schema::table('report_templates', function (Blueprint $table) {
            if (Schema::hasColumn('report_templates', 'print_settings')) {
                $table->dropColumn('print_settings');
            }
        });
    }
};
