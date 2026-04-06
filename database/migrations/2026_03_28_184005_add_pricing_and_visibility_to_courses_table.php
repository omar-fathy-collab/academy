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
        Schema::table('courses', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->default(0.00)->after('description');
            $table->boolean('is_free')->default(true)->after('price');
            $table->boolean('is_public')->default(false)->after('is_free');
            $table->boolean('is_active')->default(true)->after('is_public');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['price', 'is_free', 'is_public', 'is_active']);
        });
    }
};
