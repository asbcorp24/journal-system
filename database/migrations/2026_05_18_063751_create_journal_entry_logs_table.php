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
        Schema::create('journal_entry_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('journal_entry_id')
                ->constrained('journal_entries')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('action');
            // created, updated, deleted, status_changed, comment_added, comment_updated

            $table->string('old_status')->nullable();
            $table->string('new_status')->nullable();

            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();

            $table->text('comment')->nullable();

            $table->string('ip_address')->nullable();

            $table->timestamps();

            $table->index('journal_entry_id');
            $table->index('action');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('journal_entry_logs');
    }
};
