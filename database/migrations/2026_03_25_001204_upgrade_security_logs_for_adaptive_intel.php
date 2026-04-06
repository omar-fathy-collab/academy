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
        Schema::table('security_logs', function (Blueprint $table) {
            $table->boolean('is_false_positive')->default(false)->after('reputation_score');
            $table->foreignId('pattern_id')->nullable()->after('is_false_positive')->constrained('attack_patterns')->nullOnDelete();
            $table->integer('adaptive_weight')->default(1)->after('pattern_id');
        });
    }

    public function down(): void
    {
        Schema::table('security_logs', function (Blueprint $table) {
            $table->dropForeign(['pattern_id']);
            $table->dropColumn(['is_false_positive', 'pattern_id', 'adaptive_weight']);
        });
    }
};
