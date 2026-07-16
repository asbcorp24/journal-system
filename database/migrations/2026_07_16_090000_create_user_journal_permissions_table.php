<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_journal_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_template_id')->nullable()->constrained('journal_templates')->cascadeOnDelete();
            $table->string('access_level', 16);
            $table->timestamps();

            $table->unique(['user_id', 'division_id', 'journal_template_id', 'access_level'], 'user_journal_permissions_unique_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_journal_permissions');
    }
};
