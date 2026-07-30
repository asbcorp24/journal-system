<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_report_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_template_id')->constrained('report_templates')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'report_template_id'], 'user_report_permissions_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_report_permissions');
    }
};
