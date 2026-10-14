<?php

namespace App\Actions;

use App\Models\Ticket;
use App\Models\TicketCall;
use App\Models\User;
use App\Services\OperationalContext;
use App\TicketCallType;
use App\TicketStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RecallTicket
{
    public function __construct(
        private OperationalContext $operationalContext,
        private WarmTicketCallAnnouncementAudio $announcementAudio,
    ) {}

    public function handle(User $actor, Ticket $ticket): Ticket
    {
        Gate::forUser($actor)->authorize('recall', $ticket);

        $desk = $this->operationalContext->activeDesk($actor, session());
        $unit = $this->operationalContext->activeUnit($actor, session());

        if ($desk === null || $unit === null) {
            throw ValidationException::withMessages([
                'desk' => 'Contexto operacional inválido para rechamada.',
            ]);
        }

        return DB::transaction(function () use ($actor, $ticket, $desk, $unit): Ticket {
            $locked = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            if (
                $locked->clinic_id !== $actor->clinic_id
                || $locked->unit_id !== $unit->id
                || (int) $locked->current_desk_id !== (int) $desk->id
                || $locked->status !== TicketStatus::CALLED
            ) {
                throw ValidationException::withMessages([
                    'ticket' => 'Somente a senha chamada nesta mesa pode ser rechamada.',
                ]);
            }

            $this->assertRecallCooldown($locked);

            $calledAt = now(config('app.timezone'));

            $locked->forceFill([
                'called_at' => $calledAt,
            ])->save();

            $call = new TicketCall;
            $call->forceFill([
                'clinic_id' => $actor->clinic_id,
                'unit_id' => $unit->id,
                'sector_id' => $locked->sector_id ?? $desk->sector_id,
                'ticket_id' => $locked->id,
                'desk_id' => $desk->id,
                'called_by_user_id' => $actor->id,
                'call_type' => TicketCallType::RECALL,
                'called_at' => $calledAt,
            ])->save();

            $this->announcementAudio->schedule($call);

            return $locked->refresh()->load(['ticketType', 'currentDesk']);
        });
    }

    /**
     * Atomic cooldown: the ticket row is already locked for update, so concurrent
     * tabs serialize here and only one RECALL within the window is accepted.
     */
    private function assertRecallCooldown(Ticket $locked): void
    {
        $cooldown = max(1, (int) config('tv_realtime.recall_cooldown_seconds', 3));

        $latestRecall = TicketCall::query()
            ->where('ticket_id', $locked->id)
            ->where('clinic_id', $locked->clinic_id)
            ->where('call_type', TicketCallType::RECALL)
            ->orderByDesc('id')
            ->first();

        if ($latestRecall === null || $latestRecall->called_at === null) {
            return;
        }

        $threshold = now(config('app.timezone'))->subSeconds($cooldown);

        if ($latestRecall->called_at->greaterThan($threshold)) {
            throw ValidationException::withMessages([
                'ticket' => 'Aguarde alguns segundos antes de rechamar novamente.',
            ]);
        }
    }
}
