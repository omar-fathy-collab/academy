<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Seed Admin Types if empty
        if (DB::table('admin_types')->count() === 0) {
            DB::table('admin_types')->insert([
                [
                    'name' => 'full',
                    'label' => 'Full Admin',
                    'can_view_profits' => 1,
                    'can_manage_admins' => 1,
                    'can_manage_finances' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'partial',
                    'label' => 'Partial Admin',
                    'can_view_profits' => 0,
                    'can_manage_admins' => 0,
                    'can_manage_finances' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            ]);
        }

        // 2. Assign the first admin user to 'full' type
        $adminRole = DB::table('roles')->where('name', 'admin')->first();
        $fullAdminType = DB::table('admin_types')->where('name', 'full')->first();

        if ($adminRole && $fullAdminType) {
            DB::table('users')
                ->where('role_id', $adminRole->id)
                ->whereNull('admin_type_id')
                ->update(['admin_type_id' => $fullAdminType->id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse needed for seeding in this context
    }
};
