<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kepemilikan properti + status tinjauan. Properti buatan owner berstatus
     * "pending" (belum tampil di situs) sampai disetujui superadmin.
     * Villa lama otomatis "approved" dan tanpa owner (milik Bamboe Oerip).
     */
    public function up(): void
    {
        Schema::table('villas', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->string('review_status', 20)->default('approved')->after('is_featured'); // pending | approved | rejected
            $table->text('review_note')->nullable()->after('review_status');

            $table->index('review_status');
        });
    }

    public function down(): void
    {
        Schema::table('villas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
            $table->dropIndex(['review_status']);
            $table->dropColumn(['review_status', 'review_note']);
        });
    }
};
