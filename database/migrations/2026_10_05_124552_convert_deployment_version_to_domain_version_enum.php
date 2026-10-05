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
            ->select(['id', 'domain'])
            ->orderBy('id')
            ->chunkById(100, function ($deployments): void {
                foreach ($deployments as $deployment) {
                    DB::table('deployments')
                        ->where('id', $deployment->id)
                        ->update([
                            'version' => str_starts_with(strtolower((string) $deployment->domain), 'www.')
                                ? 'www'
                                : 'non-www',
                        ]);
                }
            });

        Schema::table('deployments', function (Blueprint $table): void {
            $table->enum('version', ['www', 'non-www'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deployments', function (Blueprint $table): void {
            $table->string('version', 20)->change();
        });
    }
};
