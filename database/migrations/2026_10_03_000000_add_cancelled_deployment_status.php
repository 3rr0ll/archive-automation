<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deployments', function (Blueprint $table): void {
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])
                ->default('pending')
                ->change();
        });
    }

    public function down(): void
    {
        DB::table('deployments')
            ->where('status', 'cancelled')
            ->update(['status' => 'failed']);

        Schema::table('deployments', function (Blueprint $table): void {
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])
                ->default('pending')
                ->change();
        });
    }
};
