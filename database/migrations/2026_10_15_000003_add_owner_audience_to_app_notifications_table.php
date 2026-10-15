<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Audience "owner": notifikasi per akun owner (user_id = id owner). */
    public function up(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE app_notifications MODIFY audience ENUM('admin','user','owner') NOT NULL");
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::table('app_notifications')->where('audience', 'owner')->delete();
            DB::statement("ALTER TABLE app_notifications MODIFY audience ENUM('admin','user') NOT NULL");
        }
    }
};
