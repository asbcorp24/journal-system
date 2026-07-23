<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'can_edit_journal_templates')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('can_edit_journal_templates')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('users', 'can_edit_journal_templates')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            $this->rollbackSqlite();
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('can_edit_journal_templates');
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
            can_edit_directory_templates tinyint(1) not null default '0',
            foreign key(division_id) references divisions(id) on delete set null
        )");

        DB::statement('INSERT INTO users_new (id, name, email, email_verified_at, password, role, division_id, is_active, remember_token, created_at, updated_at, can_edit_directory_templates) SELECT id, name, email, email_verified_at, password, role, division_id, is_active, remember_token, created_at, updated_at, can_edit_directory_templates FROM users');
        DB::statement('DROP TABLE users');
        DB::statement('ALTER TABLE users_new RENAME TO users');
        DB::statement('CREATE UNIQUE INDEX users_email_unique ON users (email)');

        DB::statement('PRAGMA foreign_keys = ON');
    }
};
