<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('directories', function (Blueprint $table) {
            $table->json('table_settings')->nullable()->after('schema');
        });
    }

    public function down()
    {
        Schema::table('directories', function (Blueprint $table) {
            $table->dropColumn('table_settings');
        });
    }
};
