<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('extracted_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_file_id')->constrained('case_files')->cascadeOnDelete();
            $table->enum('entry_type', ['error', 'warning', 'info', 'event'])->default('info');
            $table->string('code')->nullable();
            $table->string('category')->nullable();
            $table->longText('content');
            $table->timestamp('timestamp')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('extracted_entries');
    }
};
