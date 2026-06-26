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
        Schema::table('leads', function (Blueprint $table) {
            $table->string('tech_support_phone')->nullable()->after('phone');
            $table->string('store_link')->nullable()->after('tech_support_phone');
            $table->string('auth_status')->nullable()->after('store_link');
            $table->text('social_media')->nullable()->after('auth_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'tech_support_phone',
                'store_link',
                'auth_status',
                'social_media'
            ]);
        });
    }
};
