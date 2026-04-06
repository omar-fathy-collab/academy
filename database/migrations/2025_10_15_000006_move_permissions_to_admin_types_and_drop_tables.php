<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Add permission flags to admin_types
        if (Schema::hasTable('admin_types')) {
            Schema::table('admin_types', function (Blueprint $table) {
                if (! Schema::hasColumn('admin_types', 'can_view_profits')) {
                    $table->boolean('can_view_profits')->default(false)->after('label');
                }
                if (! Schema::hasColumn('admin_types', 'can_manage_admins')) {
                    $table->boolean('can_manage_admins')->default(false)->after('can_view_profits');
                }
                if (! Schema::hasColumn('admin_types', 'can_manage_finances')) {
                    $table->boolean('can_manage_finances')->default(false)->after('can_manage_admins');
                }
            });
        }

        // Drop pivot and permissions tables if they exist
        if (Schema::hasTable('admin_permission_user')) {
            Schema::dropIfExists('admin_permission_user');
        }

        if (Schema::hasTable('admin_permissions')) {
            Schema::dropIfExists('admin_permissions');
        }
    }

    public function down()
    {
        // Recreate admin_permissions table with minimal structure (rollback safe)
        if (! Schema::hasTable('admin_permissions')) {
            Schema::create('admin_permissions', function (Blueprint $table) {
                $table->id();
                $table->string('permission_key')->unique();
                $table->string('label')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_permission_user')) {
            Schema::create('admin_permission_user', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('permission_id');
                $table->primary(['user_id', 'permission_id']);
            });
        }

        // Remove the permission flags from admin_types
        if (Schema::hasTable('admin_types')) {
            Schema::table('admin_types', function (Blueprint $table) {
                if (Schema::hasColumn('admin_types', 'can_view_profits')) {
                    $table->dropColumn('can_view_profits');
                }
                if (Schema::hasColumn('admin_types', 'can_manage_admins')) {
                    $table->dropColumn('can_manage_admins');
                }
                if (Schema::hasColumn('admin_types', 'can_manage_finances')) {
                    $table->dropColumn('can_manage_finances');
                }
            });
        }
    }
};
