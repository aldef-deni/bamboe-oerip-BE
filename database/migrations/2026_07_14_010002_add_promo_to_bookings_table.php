<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('promo_id')->nullable()->after('villa_id')->constrained()->nullOnDelete();
            $table->string('promo_code')->nullable()->after('promo_id');
            $table->decimal('discount', 12, 2)->default(0)->after('service_fee');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promo_id');
            $table->dropColumn(['promo_code', 'discount']);
        });
    }
};
