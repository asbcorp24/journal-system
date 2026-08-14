<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('directory_values', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->after('directory_id');
            $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
            $table->unsignedBigInteger('deleted_by')->nullable()->after('updated_by');
            $table->softDeletes()->after('updated_at');

            $table->index('created_by');
            $table->index('updated_by');
            $table->index('deleted_by');
            $table->index('deleted_at');
        });
    }

    public function down()
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('CREATE TABLE directory_values_rollback (
                id integer not null primary key autoincrement,
                directory_id integer not null,
                value varchar not null,
                code varchar null,
                sort_order integer not null default 0,
                is_active tinyint(1) not null default 1,
                created_at datetime null,
                updated_at datetime null,
                data text null
            )');

            DB::statement('INSERT INTO directory_values_rollback (id, directory_id, value, code, sort_order, is_active, created_at, updated_at, data)
                SELECT id, directory_id, value, code, sort_order, is_active, created_at, updated_at, data
                FROM directory_values');

            DB::statement('DROP TABLE directory_values');
            DB::statement('ALTER TABLE directory_values_rollback RENAME TO directory_values');

            return;
        }

        Schema::table('directory_values', function (Blueprint $table) {
            $table->dropIndex(['created_by']);
            $table->dropIndex(['updated_by']);
            $table->dropIndex(['deleted_by']);
            $table->dropIndex(['deleted_at']);
            $table->dropColumn(['created_by', 'updated_by', 'deleted_by']);
            $table->dropSoftDeletes();
        });
    }
};
