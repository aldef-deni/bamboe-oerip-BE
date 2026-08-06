<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            $table->integer('sort_order')->default(0)->after('is_popup')->index();
        });

        // Isi urutan awal berdasarkan yang terbaru (agar tidak semua 0)
        $i = 0;
        foreach (DB::table('promos')->orderByDesc('created_at')->pluck('id') as $id) {
            DB::table('promos')->where('id', $id)->update(['sort_order' => $i++]);
        }
    }

    public function down(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
