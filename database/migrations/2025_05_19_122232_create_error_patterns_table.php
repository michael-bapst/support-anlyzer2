<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('error_patterns', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->index();
            $table->string('keyword')->nullable()->index();
            $table->string('category');
            $table->enum('severity', ['LOW', 'MEDIUM', 'HIGH'])->default('MEDIUM');
            $table->text('description')->nullable();
            $table->text('recommendation')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('error_patterns');
    }
};
