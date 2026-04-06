<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MigrateAccountantsToPartialAdminSeeder extends Seeder
{
    public function run()
    {
        // Find the admin_types ids
        $full = DB::table('admin_types')->where('name', 'full')->first();
        $partial = DB::table('admin_types')->where('name', 'partial')->first();

        if (! $partial) {
            $this->command->info('Partial admin type not found; run AdminTypesTableSeeder first.');

            return;
        }

        // Find role id for accountant
        $accountantRole = DB::table('roles')->where('role_name', 'accountant')->orWhere('role_name', 'Accountant')->first();
        if (! $accountantRole) {
            $this->command->info('No accountant role found; nothing to migrate.');

            return;
        }

        // Update users with accountant role -> role_id = 1 (admin) and admin_type_id = partial
        $count = DB::table('users')->where('role_id', $accountantRole->idroles)->update([
            'role_id' => 1,
            'admin_type_id' => $partial->id,
        ]);

        $this->command->info("Migrated {$count} accountant users to admin (partial).");
    }
}
