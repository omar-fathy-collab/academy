<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->text('html')->nullable()->after('name');
        });
    }

    public function down()
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->dropColumn('html');
        });
    }
};
