<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->string('blade_view')->nullable()->after('html');
        });
    }

    public function down()
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->dropColumn('blade_view');
        });
    }
};
