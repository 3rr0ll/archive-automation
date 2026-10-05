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
        DB::table('deployments')
            ->whereNotNull('archive_status')
            ->whereNotIn('archive_status', ['waiting', 'ready', 'failed'])
            ->update(['archive_status' => 'failed']);

        Schema::table('deployments', function (Blueprint $table): void {
            $table->enum('archive_status', ['waiting', 'ready', 'failed'])
                ->nullable()
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deployments', function (Blueprint $table): void {
            $table->string('archive_status')->nullable()->change();
        });
    }
};
