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
        Schema::create('journal_template_division', function (Blueprint $table) {
            $table->id();

            $table->foreignId('journal_template_id')
                ->constrained('journal_templates')
                ->cascadeOnDelete();

            $table->foreignId('division_id')
                ->constrained('divisions')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['journal_template_id', 'division_id'], 'journal_division_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('journal_template_division');
    }
};
