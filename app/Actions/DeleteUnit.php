<?php

namespace App\Actions;

use App\Models\Unit;
use App\Models\User;
use App\Services\OperationalContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeleteUnit
{
    public function handle(User $actor, Unit $unit): void
    {
        Gate::forUser($actor)->authorize('delete', $unit);

        abort_if($actor->clinic_id === null || (int) $unit->clinic_id !== (int) $actor->clinic_id, 404);

        DB::transaction(function () use ($actor, $unit): void {
            $fresh = Unit::query()
                ->where('clinic_id', $actor->clinic_id)
                ->whereKey($unit->id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('delete', $fresh);

            $this->archiveAttendance($fresh);
            $this->releaseOperationalContext($fresh);
            $fresh->delete();
        });
    }

    private function archiveAttendance(Unit $unit): void
    {
        $ticketScope = DB::table('tickets')
            ->where('clinic_id', $unit->clinic_id)
            ->where('unit_id', $unit->id);

        $ticketScope->update([
            'archived_unit_name' => $unit->name,
            'archived_sector_name' => DB::raw('(select sectors.name from sectors where sectors.id = tickets.sector_id)'),
            'archived_desk_name' => DB::raw('(select desks.name from desks where desks.id = tickets.current_desk_id)'),
            'updated_at' => now(),
        ]);

        DB::table('tickets')
            ->where('clinic_id', $unit->clinic_id)
            ->where('unit_id', $unit->id)
            ->update([
                'unit_id' => null,
                'sector_id' => null,
                'current_desk_id' => null,
                'target_desk_id' => null,
                'kiosk_id' => null,
                'updated_at' => now(),
            ]);

        DB::table('ticket_calls')
            ->where('clinic_id', $unit->clinic_id)
            ->where('unit_id', $unit->id)
            ->update([
                'archived_unit_name' => $unit->name,
                'archived_sector_name' => DB::raw('(select sectors.name from sectors where sectors.id = ticket_calls.sector_id)'),
                'archived_desk_name' => DB::raw('(select desks.name from desks where desks.id = ticket_calls.desk_id)'),
                'updated_at' => now(),
            ]);

        DB::table('ticket_calls')
            ->where('clinic_id', $unit->clinic_id)
            ->where('unit_id', $unit->id)
            ->update([
                'unit_id' => null,
                'sector_id' => null,
                'desk_id' => null,
                'updated_at' => now(),
            ]);

        DB::table('ticket_transfers')
            ->where('clinic_id', $unit->clinic_id)
            ->where('unit_id', $unit->id)
            ->update([
                'archived_unit_name' => $unit->name,
                'archived_from_desk_name' => DB::raw('(select desks.name from desks where desks.id = ticket_transfers.from_desk_id)'),
                'archived_to_desk_name' => DB::raw('(select desks.name from desks where desks.id = ticket_transfers.to_desk_id)'),
                'updated_at' => now(),
            ]);

        DB::table('ticket_transfers')
            ->where('clinic_id', $unit->clinic_id)
            ->where('unit_id', $unit->id)
            ->update([
                'unit_id' => null,
                'from_desk_id' => null,
                'to_desk_id' => null,
                'updated_at' => now(),
            ]);
    }

    private function releaseOperationalContext(Unit $unit): void
    {
        if ((int) session(OperationalContext::UNIT_SESSION_KEY) !== (int) $unit->id) {
            return;
        }

        session()->forget([
            OperationalContext::UNIT_SESSION_KEY,
            OperationalContext::DESK_SESSION_KEY,
        ]);
    }
}
