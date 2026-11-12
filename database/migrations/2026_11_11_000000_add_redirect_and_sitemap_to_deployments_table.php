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
        Schema::table('deployments', function (Blueprint $table): void {
            $table->unsignedBigInteger('server_id')->nullable()->change();
            $table->string('deployment_type')->default('full')->after('version');
            $table->boolean('has_sitemap')->default(true)->after('deployment_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deployments', function (Blueprint $table): void {
            $table->dropColumn(['deployment_type', 'has_sitemap']);
            $table->unsignedBigInteger('server_id')->nullable(false)->change();
        });
    }
};
