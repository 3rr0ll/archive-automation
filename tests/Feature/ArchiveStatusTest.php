<?php

namespace Tests\Feature;

use App\Enums\ArchiveStatus;
use App\Models\Deployment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ArchiveStatusTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('archiveStatuses')]
    public function test_deployment_casts_each_valid_archive_status(ArchiveStatus $archiveStatus): void
    {
        $user = User::factory()->create();

        $deployment = Deployment::create([
            'user_id' => $user->id,
            'server_id' => 123,
            'domain' => 'example.com',
            'version' => 'non-www',
            'archive_status' => $archiveStatus,
            'status' => 'pending',
            'current_step' => 'waiting_for_archive',
        ]);

        $this->assertSame($archiveStatus, $deployment->fresh()->archive_status);
    }

    /**
     * @return array<string, array{ArchiveStatus}>
     */
    public static function archiveStatuses(): array
    {
        return [
            'waiting' => [ArchiveStatus::Waiting],
            'ready' => [ArchiveStatus::Ready],
            'failed' => [ArchiveStatus::Failed],
        ];
    }

    public function test_database_rejects_invalid_archive_status(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('deployments')->insert([
            'user_id' => $user->id,
            'server_id' => 123,
            'domain' => 'example.com',
            'version' => 'non-www',
            'archive_status' => 'downloading',
            'status' => 'pending',
            'current_step' => 'waiting_for_archive',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
