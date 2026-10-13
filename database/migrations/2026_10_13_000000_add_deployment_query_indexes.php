<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deployments', function (Blueprint $table): void {
            $table->index(['status', 'created_at'], 'deployments_status_created_at_index');
            $table->index(['current_step', 'created_at'], 'deployments_current_step_created_at_index');
            $table->index(['user_id', 'created_at'], 'deployments_user_created_at_index');
            $table->index(['server_id', 'created_at'], 'deployments_server_created_at_index');
        });

        Schema::table('deployment_logs', function (Blueprint $table): void {
            $table->index(
                ['deployment_id', 'created_at', 'id'],
                'deployment_logs_deployment_created_id_index',
            );
            $table->index(
                ['deployment_id', 'status', 'created_at', 'id'],
                'deployment_logs_deployment_status_created_index',
            );
            $table->index(
                ['deployment_id', 'step_name', 'created_at', 'id'],
                'deployment_logs_deployment_step_created_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('deployment_logs', function (Blueprint $table): void {
            $table->dropIndex('deployment_logs_deployment_created_id_index');
            $table->dropIndex('deployment_logs_deployment_status_created_index');
            $table->dropIndex('deployment_logs_deployment_step_created_index');
        });

        Schema::table('deployments', function (Blueprint $table): void {
            $table->dropIndex('deployments_status_created_at_index');
            $table->dropIndex('deployments_current_step_created_at_index');
            $table->dropIndex('deployments_user_created_at_index');
            $table->dropIndex('deployments_server_created_at_index');
        });
    }
};
