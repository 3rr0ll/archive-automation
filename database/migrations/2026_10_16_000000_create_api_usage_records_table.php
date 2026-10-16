<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_usage_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('deployment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('provider', 64);
            $table->string('operation', 255);
            $table->string('method', 10);
            $table->text('endpoint');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->boolean('successful');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('requested_at', 6);
            $table->timestamps();

            $table->index(['provider', 'requested_at']);
            $table->index(['deployment_id', 'provider', 'requested_at'], 'api_usage_deployment_provider_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_usage_records');
    }
};
