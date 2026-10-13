<?php

namespace App\Services\Users;

use App\Models\User;
use App\Services\Logging\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

class UserAdministrationService
{
    public function __construct(
        private AuditLogger $auditLogger,
    ) {}

    public function delete(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw new DomainException('You cannot delete your own account.');
        }

        if ($target->isAdmin()) {
            throw new DomainException('You cannot delete another admin account.');
        }

        DB::transaction(function () use ($target): void {
            $target->tokens()->delete();
            $target->delete();
        });

        $this->auditLogger->record('user.deleted', $target, [
            'target_role' => $target->role,
        ]);
    }

    /**
     * @param  list<int>  $ids
     * @return array{deleted_ids: list<int>, deleted_count: int, skipped_admin_ids: list<int>, skipped_self: bool}
     */
    public function bulkDelete(User $actor, array $ids): array
    {
        $skippedSelf = in_array($actor->id, $ids, true);
        $users = User::query()
            ->whereKey(array_values(array_filter($ids, fn (int $id): bool => $id !== $actor->id)))
            ->get();
        $skippedAdminIds = $users
            ->filter(fn (User $user): bool => $user->isAdmin())
            ->pluck('id')
            ->values()
            ->all();
        $deletable = $users->reject(fn (User $user): bool => $user->isAdmin());
        $deletedIds = [];

        DB::transaction(function () use ($deletable, &$deletedIds): void {
            foreach ($deletable as $user) {
                $user->tokens()->delete();
                $user->delete();
                $deletedIds[] = $user->id;
            }
        });

        foreach ($deletable as $user) {
            $this->auditLogger->record('user.deleted', $user, [
                'operation' => 'bulk-delete',
                'target_role' => $user->role,
            ]);
        }

        return [
            'deleted_ids' => $deletedIds,
            'deleted_count' => count($deletedIds),
            'skipped_admin_ids' => $skippedAdminIds,
            'skipped_self' => $skippedSelf,
        ];
    }

    public function resetPassword(User $actor, User $target, string $password): void
    {
        if ($target->isAdmin() && ! $actor->is($target)) {
            throw new DomainException('You cannot reset another admin account password.');
        }

        $this->auditLogger->record('user.password-reset', $target, [
            'tokens_revoked' => true,
        ]);

        DB::transaction(function () use ($target, $password): void {
            $target->update(['password' => $password]);
            $target->tokens()->delete();
        });
    }
}
