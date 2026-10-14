<?php

namespace App\Actions;

use App\Models\Desk;
use App\Models\DeskAssignment;
use App\Models\User;
use App\Services\OperationalContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteDesk
{
    public function handle(User $actor, Desk $desk): void
    {
        Gate::forUser($actor)->authorize('delete', $desk);

        abort_if($actor->clinic_id === null || (int) $desk->clinic_id !== (int) $actor->clinic_id, 404);

        $this->assertCanDetach($desk);

        DB::transaction(function () use ($actor, $desk): void {
            $fresh = Desk::query()
                ->where('clinic_id', $actor->clinic_id)
                ->whereKey($desk->id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('delete', $fresh);
            $this->assertCanDetach($fresh);
            $this->archiveHistory($fresh);

            DeskAssignment::query()
                ->where('clinic_id', $fresh->clinic_id)
                ->where('desk_id', $fresh->id)
                ->delete();

            if ((int) session(OperationalContext::DESK_SESSION_KEY) === (int) $fresh->id) {
                session()->forget(OperationalContext::DESK_SESSION_KEY);
            }

            $fresh->delete();
        });
    }

    private function assertCanDetach(Desk $desk): void
    {
        if ($desk->hasOpenAttendance()) {
            throw ValidationException::withMessages([
                'desk' => 'Esta mesa possui um atendimento em andamento. Finalize ou transfira o atendimento antes de excluir.',
            ]);
        }

        if ($desk->hasWaitingTargetedTickets()) {
            throw ValidationException::withMessages([
                'desk' => 'Esta mesa possui senhas aguardando direcionadas a ela. Redirecione essas senhas antes de excluir.',
            ]);
        }
    }

    private function archiveHistory(Desk $desk): void
    {
        $clinicId = $desk->clinic_id;
        $deskId = $desk->id;
        $name = $desk->name;
        $now = now();

        DB::table('tickets')
            ->where('clinic_id', $clinicId)
            ->where('current_desk_id', $deskId)
            ->whereNull('archived_desk_name')
            ->update([
                'archived_desk_name' => $name,
                'updated_at' => $now,
            ]);

        DB::table('tickets')
            ->where('clinic_id', $clinicId)
            ->where('current_desk_id', $deskId)
            ->update([
                'current_desk_id' => null,
                'updated_at' => $now,
            ]);

        DB::table('tickets')
            ->where('clinic_id', $clinicId)
            ->where('target_desk_id', $deskId)
            ->update([
                'target_desk_id' => null,
                'updated_at' => $now,
            ]);

        DB::table('ticket_calls')
            ->where('clinic_id', $clinicId)
            ->where('desk_id', $deskId)
            ->whereNull('archived_desk_name')
            ->update([
                'archived_desk_name' => $name,
                'updated_at' => $now,
            ]);

        DB::table('ticket_calls')
            ->where('clinic_id', $clinicId)
            ->where('desk_id', $deskId)
            ->update([
                'desk_id' => null,
                'updated_at' => $now,
            ]);

        DB::table('ticket_transfers')
            ->where('clinic_id', $clinicId)
            ->where('from_desk_id', $deskId)
            ->whereNull('archived_from_desk_name')
            ->update([
                'archived_from_desk_name' => $name,
                'updated_at' => $now,
            ]);

        DB::table('ticket_transfers')
            ->where('clinic_id', $clinicId)
            ->where('from_desk_id', $deskId)
            ->update([
                'from_desk_id' => null,
                'updated_at' => $now,
            ]);

        DB::table('ticket_transfers')
            ->where('clinic_id', $clinicId)
            ->where('to_desk_id', $deskId)
            ->whereNull('archived_to_desk_name')
            ->update([
                'archived_to_desk_name' => $name,
                'updated_at' => $now,
            ]);

        DB::table('ticket_transfers')
            ->where('clinic_id', $clinicId)
            ->where('to_desk_id', $deskId)
            ->update([
                'to_desk_id' => null,
                'updated_at' => $now,
            ]);
    }
}
