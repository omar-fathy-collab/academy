<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('admin_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g. 'full', 'partial'
            $table->string('label')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('admin_type_id')->nullable()->after('role_id');
            $table->foreign('admin_type_id')->references('id')->on('admin_types')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'admin_type_id')) {
                $table->dropForeign(['admin_type_id']);
                $table->dropColumn('admin_type_id');
            }
        });

        Schema::dropIfExists('admin_types');
    }
};
