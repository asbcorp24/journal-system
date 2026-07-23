<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_edit_directory_templates')->default(false);
        });

        Schema::table('directories', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->index();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->rollbackSqlite();
            return;
        }

        Schema::table('directories', function (Blueprint $table) {
            $table->dropIndex(['created_by']);
            $table->dropColumn('created_by');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('can_edit_directory_templates');
        });
    }

    private function rollbackSqlite(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        DB::statement("CREATE TABLE users_new (
            id integer not null primary key autoincrement,
            name varchar not null,
            email varchar not null,
            email_verified_at datetime,
            password text not null,
            role varchar not null default 'worker',
            division_id integer,
            is_active tinyint(1) not null default '1',
            remember_token varchar,
            created_at datetime,
            updated_at datetime,
            foreign key(division_id) references divisions(id) on delete set null
        )");
        DB::statement('INSERT INTO users_new (id, name, email, email_verified_at, password, role, division_id, is_active, remember_token, created_at, updated_at) SELECT id, name, email, email_verified_at, password, role, division_id, is_active, remember_token, created_at, updated_at FROM users');
        DB::statement('DROP TABLE users');
        DB::statement('ALTER TABLE users_new RENAME TO users');
        DB::statement('CREATE UNIQUE INDEX users_email_unique ON users (email)');

        DB::statement('CREATE TABLE directories_new (id integer not null primary key autoincrement, name varchar not null, code varchar, description text, created_at datetime, updated_at datetime, schema text)');
        DB::statement('INSERT INTO directories_new (id, name, code, description, created_at, updated_at, schema) SELECT id, name, code, description, created_at, updated_at, schema FROM directories');
        DB::statement('DROP TABLE directories');
        DB::statement('ALTER TABLE directories_new RENAME TO directories');

        DB::statement('PRAGMA foreign_keys = ON');
    }
};
