<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Role baru "owner" (pemilik properti — ekstranet owner.bamboeoerip.com)
     * + flag is_active agar superadmin bisa menonaktifkan akun.
     */
    public function up(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE users MODIFY role ENUM('user','admin','owner') NOT NULL DEFAULT 'user'");
        }

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::table('users')->where('role', 'owner')->update(['role' => 'user']);
            DB::statement("ALTER TABLE users MODIFY role ENUM('user','admin') NOT NULL DEFAULT 'user'");
        }
    }
};
