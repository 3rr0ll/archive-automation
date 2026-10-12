<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deployments', function (Blueprint $table): void {
            $table->string('archive_source')->default('url')->after('version');
            $table->text('archive_path')->nullable()->after('archive_url');
            $table->string('archive_original_name')->nullable()->after('archive_path');
        });
    }

    public function down(): void
    {
        Schema::table('deployments', function (Blueprint $table): void {
            $table->dropColumn([
                'archive_source',
                'archive_path',
                'archive_original_name',
            ]);
        });
    }
};
