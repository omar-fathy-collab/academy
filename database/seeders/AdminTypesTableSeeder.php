<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdminTypesTableSeeder extends Seeder
{
    public function run()
    {
        DB::table('admin_types')->insertOrIgnore([
            [
                'name' => 'full',
                'label' => 'Full Admin',
                'can_view_profits' => true,
                'can_manage_admins' => true,
                'can_manage_finances' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'partial',
                'label' => 'Partial Admin',
                'can_view_profits' => false,
                'can_manage_admins' => false,
                'can_manage_finances' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
