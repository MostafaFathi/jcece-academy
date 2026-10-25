<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->char('currency', 3)->nullable();
        });
        foreach (['courses', 'packages', 'coupons'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->char('currency', 3)->nullable();
            });
        }

        DB::table('site_settings')->whereNull('currency')->update(['currency' => 'ILS']);
        foreach (['courses', 'packages', 'coupons'] as $tableName) {
            DB::table($tableName)->whereNull('currency')->update(['currency' => 'ILS']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['courses', 'packages', 'coupons'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('currency');
            });
        }
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn('currency');
        });
    }
};
