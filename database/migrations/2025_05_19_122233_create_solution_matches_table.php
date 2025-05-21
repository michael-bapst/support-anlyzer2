<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('solution_matches', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->index();
            $table->string('keyword')->nullable()->index();
            $table->text('solution_text');
            $table->json('tags')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('solution_matches');
    }
};
