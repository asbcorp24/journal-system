<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('directories', function (Blueprint $table) {
            $table->json('schema')->nullable()->after('description');
        });

        Schema::table('directory_values', function (Blueprint $table) {
            $table->json('data')->nullable()->after('value');
        });
    }

    public function down()
    {
        Schema::table('directory_values', function (Blueprint $table) {
            $table->dropColumn('data');
        });

        Schema::table('directories', function (Blueprint $table) {
            $table->dropColumn('schema');
        });
    }
};
