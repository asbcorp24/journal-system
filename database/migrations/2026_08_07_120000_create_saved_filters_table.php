<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type', 50);
            $table->unsignedBigInteger('entity_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('visible_fields')->nullable();
            $table->json('values')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'entity_type', 'entity_id'], 'saved_filters_user_entity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_filters');
    }
};
