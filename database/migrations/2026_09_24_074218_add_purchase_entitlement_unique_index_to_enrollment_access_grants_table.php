<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('enrollment_access_grants', function (Blueprint $table) {
            $table->string('purchase_entitlement_key', 100)
                ->nullable()
                ->after('source_id');
            $table->unique('purchase_entitlement_key', 'access_grants_purchase_entitlement_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enrollment_access_grants', function (Blueprint $table) {
            $table->dropUnique('access_grants_purchase_entitlement_unique');
            $table->dropColumn('purchase_entitlement_key');
        });
    }
};
