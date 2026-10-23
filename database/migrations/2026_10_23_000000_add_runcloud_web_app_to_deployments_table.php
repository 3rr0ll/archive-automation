<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deployments', function (Blueprint $table): void {
            $table->unsignedBigInteger('runcloud_web_app_id')->nullable()->after('server_id');
            $table->string('runcloud_web_app_name')->nullable()->after('runcloud_web_app_id');
        });
    }

    public function down(): void
    {
        Schema::table('deployments', function (Blueprint $table): void {
            $table->dropColumn(['runcloud_web_app_id', 'runcloud_web_app_name']);
        });
    }
};
