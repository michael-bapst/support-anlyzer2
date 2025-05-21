<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('extracted_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_file_id')->constrained('case_files')->cascadeOnDelete(); // kein ->index() nötig
            $table->enum('entry_type', ['error', 'warning', 'info', 'event'])->default('info')->index();
            $table->string('code')->nullable()->index();
            $table->string('category')->nullable();
            $table->longText('content');
            $table->timestamp('timestamp')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->enum('severity', ['LOW', 'MEDIUM', 'HIGH'])->nullable()->index();
            $table->foreignId('pattern_id')->nullable()->constrained('error_patterns')->nullOnDelete(); // kein ->index()
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::table('extracted_entries', function (Blueprint $table) {
            $table->dropForeign(['case_file_id']);
            $table->dropForeign(['pattern_id']);
        });

        Schema::dropIfExists('extracted_entries');
    }
};
