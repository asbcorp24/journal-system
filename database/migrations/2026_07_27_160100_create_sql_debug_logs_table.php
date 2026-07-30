<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sql_debug_logs', function (Blueprint $table) {
            $table->id();
            $table->string('connection_name', 100)->nullable();
            $table->string('driver', 50)->nullable();
            $table->longText('sql');
            $table->longText('bindings')->nullable();
            $table->decimal('time_ms', 10, 3)->nullable();
            $table->string('request_path')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sql_debug_logs');
    }
};
