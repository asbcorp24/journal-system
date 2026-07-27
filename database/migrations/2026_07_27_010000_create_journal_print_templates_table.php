<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('journal_print_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_template_id')
                ->constrained('journal_templates')
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['journal_template_id', 'is_active']);
            $table->index('created_by');
        });
    }

    public function down()
    {
        Schema::dropIfExists('journal_print_templates');
    }
};
