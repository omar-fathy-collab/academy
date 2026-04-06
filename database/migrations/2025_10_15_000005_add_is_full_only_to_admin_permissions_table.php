<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('admin_permissions', function (Blueprint $table) {
            $table->boolean('is_full_only')->default(false)->after('description');
        });
    }

    public function down()
    {
        Schema::table('admin_permissions', function (Blueprint $table) {
            if (Schema::hasColumn('admin_permissions', 'is_full_only')) {
                $table->dropColumn('is_full_only');
            }
        });
    }
};
