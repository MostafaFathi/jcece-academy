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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('password');
            $table->string('avatar')->nullable()->after('phone');
            $table->string('country')->nullable()->after('avatar');
            $table->string('city')->nullable()->after('country');
            $table->string('specialization')->nullable()->after('city');
            $table->string('status')->default('active')->index()->after('specialization');
            $table->timestamp('last_login_at')->nullable()->after('status');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'avatar',
                'country',
                'city',
                'specialization',
                'status',
                'last_login_at',
                'deleted_at',
            ]);
        });
    }
};
