<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('journal_entry_comments', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('journal_entry_id')
                ->constrained('journal_entry_comments')
                ->nullOnDelete();

            $table->timestamp('edited_at')->nullable()->after('comment');

            $table->foreignId('edited_by')
                ->nullable()
                ->after('edited_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('parent_id');
        });
    }

    public function down()
    {
        Schema::table('journal_entry_comments', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropForeign(['edited_by']);

            $table->dropColumn([
                'parent_id',
                'edited_at',
                'edited_by',
            ]);
        });
    }
    /**
     * Reverse the migrations.
     *
     * @return void
     */

};
