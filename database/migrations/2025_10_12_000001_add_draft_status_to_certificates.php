<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Add 'draft' to the certificates.status enum
        // Only run if the table exists (avoids crash during fresh migrate)
        if (Schema::hasTable('certificates') && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `certificates` MODIFY `status` ENUM('issued','revoked','draft') NOT NULL DEFAULT 'issued'");
        }
    }

    public function down()
    {
        if (Schema::hasTable('certificates')) {
            DB::statement("ALTER TABLE `certificates` MODIFY `status` ENUM('issued','revoked') NOT NULL DEFAULT 'issued'");
        }
    }
};
