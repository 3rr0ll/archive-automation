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
            $table->text('archive_url')->nullable()->after('version');
            $table->string('archive_status')->nullable()->after('archive_url')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deployments', function (Blueprint $table): void {
            $table->dropIndex(['archive_status']);
            $table->dropColumn(['archive_url', 'archive_status']);
        });
    }
};
