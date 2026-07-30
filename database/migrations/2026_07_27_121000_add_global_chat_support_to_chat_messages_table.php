<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->rebuildChatMessagesTable(true);
    }

    public function down(): void
    {
        $this->rebuildChatMessagesTable(false);
    }

    private function rebuildChatMessagesTable(bool $withGlobalChat): void
    {
        DB::transaction(function () use ($withGlobalChat) {
            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = OFF');
            }

            Schema::create('chat_messages_tmp', function (Blueprint $table) use ($withGlobalChat) {
                $table->id();
                $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('recipient_id')
                    ->nullable($withGlobalChat)
                    ->constrained('users')
                    ->cascadeOnDelete();

                if ($withGlobalChat) {
                    $table->boolean('is_global')->default(false);
                }

                $table->text('message');
                $table->boolean('is_read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['sender_id', 'recipient_id']);
                $table->index(['recipient_id', 'is_read']);
                $table->index('created_at');

                if ($withGlobalChat) {
                    $table->index(['is_global', 'created_at']);
                }
            });

            if ($withGlobalChat) {
                DB::statement('
                    INSERT INTO chat_messages_tmp (
                        id, sender_id, recipient_id, is_global, message, is_read, read_at, created_at, updated_at
                    )
                    SELECT
                        id, sender_id, recipient_id, 0, message, is_read, read_at, created_at, updated_at
                    FROM chat_messages
                ');
            } else {
                DB::statement('
                    INSERT INTO chat_messages_tmp (
                        id, sender_id, recipient_id, message, is_read, read_at, created_at, updated_at
                    )
                    SELECT
                        id,
                        sender_id,
                        COALESCE(recipient_id, sender_id),
                        message,
                        is_read,
                        read_at,
                        created_at,
                        updated_at
                    FROM chat_messages
                ');
            }

            Schema::drop('chat_messages');
            Schema::rename('chat_messages_tmp', 'chat_messages');

            if ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON');
            }
        });
    }
};
