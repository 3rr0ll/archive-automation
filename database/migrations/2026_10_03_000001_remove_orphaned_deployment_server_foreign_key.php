<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deployments', function (Blueprint $table): void {
            $table->dropForeign(['server_id']);
        });
    }

    public function down(): void
    {
        // The servers table was removed by an earlier migration, so the
        // orphaned constraint cannot safely be restored.
    }
};
