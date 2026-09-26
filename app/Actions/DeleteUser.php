<?php

namespace App\Actions;

use App\Exceptions\LastAdministratorProtectedException;
use App\Models\User;
use App\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class DeleteUser
{
    public function handle(User $actor, User $user): void
    {
        Gate::forUser($actor)->authorize('delete', $user);

        abort_if($actor->clinic_id === null || (int) $user->clinic_id !== (int) $actor->clinic_id, 404);
        abort_if((int) $actor->id === (int) $user->id, 403);

        DB::transaction(function () use ($actor, $user): void {
            $fresh = User::query()
                ->where('clinic_id', $actor->clinic_id)
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('delete', $fresh);
            abort_if((int) $actor->id === (int) $fresh->id, 403);
            $this->guardLastAdministrator($fresh);

            $this->archiveActorReferences($fresh);

            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $fresh->id)->delete();
            }

            $fresh->delete();
        });
    }

    private function guardLastAdministrator(User $target): void
    {
        if ($target->role !== UserRole::ADMINISTRATOR || ! $target->active) {
            return;
        }

        $anotherActiveAdministrator = User::query()
            ->where('clinic_id', $target->clinic_id)
            ->where('role', UserRole::ADMINISTRATOR)
            ->where('active', true)
            ->whereKeyNot($target->id)
            ->lockForUpdate()
            ->exists();

        if (! $anotherActiveAdministrator) {
            throw LastAdministratorProtectedException::cannotDelete();
        }
    }

    private function archiveActorReferences(User $user): void
    {
        $clinicId = $user->clinic_id;
        $userId = $user->id;
        $name = $user->name;

        $this->snapshotAndNull('tickets', $clinicId, 'called_by_user_id', 'archived_called_by_name', $userId, $name);
        $this->snapshotAndNull('tickets', $clinicId, 'started_by_user_id', 'archived_started_by_name', $userId, $name);
        $this->snapshotAndNull('tickets', $clinicId, 'completed_by_user_id', 'archived_completed_by_name', $userId, $name);
        $this->snapshotAndNull('tickets', $clinicId, 'no_show_by_user_id', 'archived_no_show_by_name', $userId, $name);
        $this->snapshotAndNull('ticket_calls', $clinicId, 'called_by_user_id', 'archived_called_by_name', $userId, $name);
        $this->snapshotAndNull('ticket_transfers', $clinicId, 'transferred_by_user_id', 'archived_transferred_by_name', $userId, $name);
        $this->snapshotAndNull('messages', $clinicId, 'sender_id', 'archived_sender_name', $userId, $name);

        DB::table('media_items')
            ->where('created_by_user_id', $userId)
            ->update([
                'archived_created_by_name' => $name,
                'updated_at' => now(),
            ]);
    }

    private function snapshotAndNull(string $table, int $clinicId, string $column, string $archiveColumn, int $userId, string $name): void
    {
        DB::table($table)
            ->where('clinic_id', $clinicId)
            ->where($column, $userId)
            ->update([
                $archiveColumn => $name,
                'updated_at' => now(),
            ]);

        DB::table($table)
            ->where('clinic_id', $clinicId)
            ->where($column, $userId)
            ->update([
                $column => null,
                'updated_at' => now(),
            ]);
    }
}
