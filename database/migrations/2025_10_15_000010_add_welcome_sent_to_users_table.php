<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWelcomeSentToUsersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'welcome_sent')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('welcome_sent')->nullable()->default(false)->after('is_active');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'welcome_sent')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('welcome_sent');
            });
        }
    }
}
