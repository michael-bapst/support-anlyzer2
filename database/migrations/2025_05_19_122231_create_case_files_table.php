<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('case_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->string('filename');
            $table->string('path');
            $table->string('extension')->nullable();
            $table->integer('size_kb')->nullable();
            $table->string('hash')->nullable();
            $table->boolean('parsed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::table('case_files', function (Blueprint $table) {
            $table->dropForeign(['case_id']);
        });

        Schema::dropIfExists('case_files');
    }
};
