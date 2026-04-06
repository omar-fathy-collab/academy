<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attack_patterns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('payload'); // payload, sequence, path
            $table->text('signature'); // JSON or regex string
            $table->integer('confidence_score')->default(50); // 0-100
            $table->integer('hit_count')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attack_patterns');
    }
};
