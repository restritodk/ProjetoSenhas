<?php

namespace App\Services;

use App\Models\Desk;
use App\Models\DisplayPanel;
use App\Models\Ticket;
use App\Models\TicketCall;
use App\Models\TicketTransfer;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\User;
use App\TicketCallType;
use App\TicketStatus;
use App\TicketTransferType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AdminDashboardMetricsService
{
    /**
     * Clinic dashboard snapshot.
     *
     * Scope is always the authenticated user's clinic and the units they may operate.
     * A requested unit outside that set is rejected. Roughly 12–16 queries per render:
     * units, status aggregates, issued rows for the chart, previous-period counts,
     * average wait, desks, open tickets, panels and a short activity window.
     *
     * @return array<string, mixed>
     */
    public function snapshot(
        User $user,
        string $period,
        ?string $customFrom,
        ?string $customTo,
        ?int $unitId,
    ): array {
        abort_unless($user->clinic_id !== null && $user->hasPermission('dashboard.view'), 403);

        [$from, $to, $periodLabel, $comparisonLabel] = $this->resolvePeriod($period, $customFrom, $customTo);
        $duration = max(1, $to->getTimestamp() - $from->getTimestamp());
        $previousTo = $from->subSecond();
        $previousFrom = $previousTo->subSeconds($duration);

        $units = $this->allowedUnits($user);
        $allowedIds = $units->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($unitId !== null && ! in_array($unitId, $allowedIds, true)) {
            abort(403);
        }

        $unitIds = $unitId !== null ? [$unitId] : $allowedIds;
        $clinicId = (int) $user->clinic_id;
        $selected = $unitId !== null ? $units->firstWhere('id', $unitId) : null;

        $empty = $this->blank(
            $user,
            $period,
            $periodLabel,
            $comparisonLabel,
            $from,
            $to,
            $selected instanceof Unit ? $selected->name : ($units->count() === 1 ? (string) $units->first()->name : 'Todas as unidades'),
        );

        if ($unitIds === []) {
            return $empty;
        }

        $issuedRows = Ticket::query()
            ->where('clinic_id', $clinicId)
            ->whereIn('unit_id', $unitIds)
            ->whereBetween('issued_at', [$from, $to])
            ->get(['ticket_type_id', 'issued_at']);

        $waitingByType = Ticket::query()
            ->where('clinic_id', $clinicId)
            ->whereIn('unit_id', $unitIds)
            ->where('status', TicketStatus::WAITING)
            ->selectRaw('ticket_type_id, COUNT(*) as aggregate')
            ->groupBy('ticket_type_id')
            ->get();

        $inService = (int) Ticket::query()
            ->where('clinic_id', $clinicId)
            ->whereIn('unit_id', $unitIds)
            ->where('status', TicketStatus::IN_SERVICE)
            ->count();

        $completed = (int) Ticket::query()
            ->where('clinic_id', $clinicId)
            ->whereIn('unit_id', $unitIds)
            ->where('status', TicketStatus::COMPLETED)
            ->whereBetween('completed_at', [$from, $to])
            ->count();

        $previousIssued = (int) Ticket::query()
            ->where('clinic_id', $clinicId)
            ->whereIn('unit_id', $unitIds)
            ->whereBetween('issued_at', [$previousFrom, $previousTo])
            ->count();

        $previousCompleted = (int) Ticket::query()
            ->where('clinic_id', $clinicId)
            ->whereIn('unit_id', $unitIds)
            ->where('status', TicketStatus::COMPLETED)
            ->whereBetween('completed_at', [$previousFrom, $previousTo])
            ->count();

        $wait = $this->averageWaitSeconds($clinicId, $unitIds, $from, $to);

        $typeIds = $issuedRows->pluck('ticket_type_id')
            ->merge($waitingByType->pluck('ticket_type_id'))
            ->filter()
            ->unique()
            ->values();

        $types = $typeIds->isEmpty()
            ? collect()
            : TicketType::query()
                ->where('clinic_id', $clinicId)
                ->whereIn('id', $typeIds)
                ->get(['id', 'name', 'prefix', 'priority'])
                ->keyBy('id');

        $issuedCount = $issuedRows->count();
        $waitingCount = (int) $waitingByType->sum(fn ($row): int => (int) $row->aggregate);
        $granularity = $period === 'today' ? 'hour' : 'day';

        $desks = $this->desks($clinicId, $unitIds);
        $panels = $this->panels($clinicId, $unitIds);
        $queue = $this->queue($waitingByType, $types, $waitingCount);

        return [
            'period_key' => $period,
            'period_label' => $periodLabel,
            'comparison_label' => $comparisonLabel,
            'clinic_name' => (string) ($user->clinic?->name ?? 'Clínica'),
            'unit_label' => $selected instanceof Unit
                ? $selected->name
                : ($units->count() === 1 ? (string) $units->first()->name : 'Todas as unidades'),
            'issued' => [
                'label' => $period === 'today' ? 'Senhas hoje' : 'Senhas no período',
                'value' => $issuedCount,
                'delta' => $this->deltaPercent($issuedCount, $previousIssued),
            ],
            'waiting' => [
                'value' => $waitingCount,
                'hint' => $waitingCount === 0 ? 'Nenhuma senha aguardando.' : 'em fila agora',
            ],
            'in_service' => [
                'value' => $inService,
                'hint' => $inService === 0 ? 'Nenhum atendimento em andamento.' : 'agora',
            ],
            'completed' => [
                'label' => $period === 'today' ? 'Finalizadas hoje' : 'Finalizadas no período',
                'value' => $completed,
                'delta' => $this->deltaPercent($completed, $previousCompleted),
            ],
            'wait' => [
                'seconds' => $wait['seconds'],
                'samples' => $wait['samples'],
                'display' => $wait['seconds'] === null ? '—' : $this->formatClock($wait['seconds']),
                'hint' => $wait['samples'] === 0 ? 'Sem chamadas no período.' : 'da emissão até a 1ª chamada',
            ],
            'desks_summary' => $desks['summary'],
            'chart' => $this->chart($issuedRows, $types, $from, $to, $granularity),
            'queue' => $queue,
            'desks' => $desks['rows'],
            'panels' => $panels,
            'activity' => $this->activity($clinicId, $unitIds, $from, $to),
            'can_view_desks' => Gate::forUser($user)->allows('viewAny', Desk::class),
            'can_view_panels' => Gate::forUser($user)->allows('viewAny', DisplayPanel::class),
        ];
    }

    /**
     * @return Collection<int, Unit>
     */
    public function unitsFor(User $user): Collection
    {
        return $this->allowedUnits($user);
    }

    /**
     * @return Collection<int, Unit>
     */
    private function allowedUnits(User $user): Collection
    {
        $query = Unit::query()
            ->where('clinic_id', $user->clinic_id)
            ->where('active', true)
            ->orderBy('name');

        if (! $user->isAdministrator()) {
            $query->whereIn('id', $user->units()->select('units.id'));
        }

        return $query->get(['id', 'clinic_id', 'name', 'active']);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string, 3: string}
     */
    private function resolvePeriod(string $period, ?string $customFrom, ?string $customTo): array
    {
        $tz = (string) config('app.timezone');
        $now = CarbonImmutable::now($tz);

        return match ($period) {
            'today' => [$now->startOfDay(), $now->endOfDay(), 'Hoje', 'ontem'],
            '7d' => [$now->subDays(6)->startOfDay(), $now->endOfDay(), 'Últimos 7 dias', 'período anterior'],
            '30d' => [$now->subDays(29)->startOfDay(), $now->endOfDay(), 'Últimos 30 dias', 'período anterior'],
            'custom' => $this->resolveCustomPeriod($customFrom, $customTo, $tz),
            default => throw ValidationException::withMessages([
                'period' => 'Período inválido.',
            ]),
        };
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string, 3: string}
     */
    private function resolveCustomPeriod(?string $customFrom, ?string $customTo, string $tz): array
    {
        if ($customFrom === null || $customTo === null || $customFrom === '' || $customTo === '') {
            throw ValidationException::withMessages([
                'customFrom' => 'Informe a data inicial e a data final.',
            ]);
        }

        try {
            $from = CarbonImmutable::parse($customFrom, $tz)->startOfDay();
            $to = CarbonImmutable::parse($customTo, $tz)->endOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'customFrom' => 'Datas inválidas.',
            ]);
        }

        if ($from->greaterThan($to)) {
            throw ValidationException::withMessages([
                'customFrom' => 'A data inicial deve ser anterior ou igual à data final.',
            ]);
        }

        if ($this->inclusiveDaySpan($from, $to) > 90) {
            throw ValidationException::withMessages([
                'customFrom' => 'O intervalo personalizado pode ter no máximo 90 dias.',
            ]);
        }

        return [$from, $to, 'Personalizado', 'período anterior'];
    }

    private function inclusiveDaySpan(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) floor(($to->startOfDay()->getTimestamp() - $from->startOfDay()->getTimestamp()) / 86400);
    }

    /**
     * Espera = emissão (issued_at) até a primeira chamada inicial (ticket_calls.call_type = initial).
     * Registros sem essa chamada, ou com duração negativa, não entram na média.
     *
     * @param  list<int>  $unitIds
     * @return array{seconds: int|null, samples: int}
     */
    private function averageWaitSeconds(int $clinicId, array $unitIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $firstCalls = DB::table('ticket_calls')
            ->select('ticket_id', DB::raw('MIN(called_at) as first_called_at'))
            ->where('clinic_id', $clinicId)
            ->whereIn('unit_id', $unitIds)
            ->where('call_type', TicketCallType::INITIAL->value)
            ->groupBy('ticket_id');

        $expr = match (DB::connection()->getDriverName()) {
            'sqlite' => '(julianday(first_calls.first_called_at) - julianday(tickets.issued_at)) * 86400.0',
            default => 'TIMESTAMPDIFF(SECOND, tickets.issued_at, first_calls.first_called_at)',
        };

        $row = DB::table('tickets')
            ->joinSub($firstCalls, 'first_calls', 'first_calls.ticket_id', '=', 'tickets.id')
            ->where('tickets.clinic_id', $clinicId)
            ->whereIn('tickets.unit_id', $unitIds)
            ->whereBetween('tickets.issued_at', [$from, $to])
            ->whereColumn('first_calls.first_called_at', '>=', 'tickets.issued_at')
            ->selectRaw("AVG({$expr}) as aggregate, COUNT(*) as samples")
            ->first();

        $samples = (int) ($row->samples ?? 0);

        if ($samples === 0 || $row?->aggregate === null) {
            return ['seconds' => null, 'samples' => 0];
        }

        return [
            'seconds' => (int) round((float) $row->aggregate),
            'samples' => $samples,
        ];
    }

    /**
     * @param  Collection<int, Ticket>  $issuedRows
     * @param  Collection<int|string, TicketType>  $types
     * @return array<string, mixed>
     */
    private function chart(Collection $issuedRows, Collection $types, CarbonImmutable $from, CarbonImmutable $to, string $granularity): array
    {
        $subtitle = $granularity === 'hour'
            ? 'Quantidade de senhas por horário'
            : 'Quantidade de senhas por dia';

        if ($issuedRows->isEmpty()) {
            return [
                'subtitle' => $subtitle,
                'empty' => true,
                'legend' => [],
                'areas' => [],
                'line' => '',
                'y_labels' => [],
                'x_labels' => [],
                'columns' => [],
                'buckets' => [],
            ];
        }

        $tz = (string) config('app.timezone');
        $bucketKeys = $this->bucketKeys($issuedRows, $from, $to, $granularity, $tz);
        $seriesTypes = $types->sortByDesc('priority')->values();
        $counts = [];

        foreach ($bucketKeys as $key) {
            $counts[$key] = [];
            foreach ($seriesTypes as $type) {
                $counts[$key][$type->id] = 0;
            }
        }

        foreach ($issuedRows as $ticket) {
            $key = $this->bucketKey($ticket->issued_at, $granularity, $tz);
            if (! isset($counts[$key])) {
                continue;
            }
            $typeId = (int) $ticket->ticket_type_id;
            if (! isset($counts[$key][$typeId])) {
                $counts[$key][$typeId] = 0;
            }
            $counts[$key][$typeId]++;
        }

        $paletteIndex = 0;
        $series = [];
        foreach ($seriesTypes as $type) {
            $color = $this->colorForType($type);
            if ($color === null) {
                $color = $this->palette($paletteIndex);
                $paletteIndex++;
            }
            $values = [];
            foreach ($bucketKeys as $key) {
                $values[] = (int) ($counts[$key][$type->id] ?? 0);
            }
            $series[] = [
                'name' => $type->name,
                'prefix' => $type->prefix,
                'color' => $color,
                'values' => $values,
            ];
        }

        $buckets = [];
        foreach ($bucketKeys as $index => $key) {
            $parts = [];
            $total = 0;
            foreach ($series as $item) {
                $value = $item['values'][$index];
                $total += $value;
                if ($value > 0) {
                    $parts[] = [
                        'name' => $item['name'],
                        'count' => $value,
                        'color' => $item['color'],
                    ];
                }
            }
            $buckets[] = [
                'label' => $this->bucketLabel($key, $granularity),
                'total' => $total,
                'parts' => $parts,
            ];
        }

        $geometry = $this->geometry($buckets, $series);

        return [
            'subtitle' => $subtitle,
            'empty' => false,
            'legend' => array_merge(
                [['name' => 'Total', 'color' => '#1e3a5f']],
                array_map(fn (array $item): array => ['name' => $item['name'], 'color' => $item['color']], $series),
            ),
            'areas' => $geometry['areas'],
            'line' => $geometry['line'],
            'y_labels' => $geometry['y_labels'],
            'x_labels' => $geometry['x_labels'],
            'columns' => $geometry['columns'],
            'buckets' => $buckets,
        ];
    }

    /**
     * @param  Collection<int, Ticket>  $issuedRows
     * @return list<string>
     */
    private function bucketKeys(Collection $issuedRows, CarbonImmutable $from, CarbonImmutable $to, string $granularity, string $tz): array
    {
        if ($granularity === 'day') {
            $keys = [];
            $cursor = $from->startOfDay();
            $end = $to->startOfDay();
            while ($cursor->lessThanOrEqualTo($end)) {
                $keys[] = $cursor->format('Y-m-d');
                $cursor = $cursor->addDay();
            }

            return $keys;
        }

        $hours = $issuedRows
            ->map(fn (Ticket $ticket): int => (int) $ticket->issued_at->timezone($tz)->format('G'))
            ->all();

        $start = min(7, ...$hours);
        $end = max(17, ...$hours);
        $day = $from->timezone($tz)->toDateString();
        $keys = [];

        for ($hour = $start; $hour <= $end; $hour++) {
            $keys[] = sprintf('%s %02d:00', $day, $hour);
        }

        return $keys;
    }

    private function bucketKey(CarbonInterface $issuedAt, string $granularity, string $tz): string
    {
        $local = $issuedAt->timezone($tz);

        return $granularity === 'hour'
            ? $local->format('Y-m-d H:00')
            : $local->format('Y-m-d');
    }

    private function bucketLabel(string $key, string $granularity): string
    {
        if ($granularity === 'hour') {
            return CarbonImmutable::parse($key)->format('H:i');
        }

        return CarbonImmutable::parse($key)->format('d/m');
    }

    /**
     * @param  list<array{label: string, total: int, parts: list<array{name: string, count: int, color: string}>}>  $buckets
     * @param  list<array{name: string, prefix: string, color: string, values: list<int>}>  $series
     * @return array{areas: list<array{d: string, color: string}>, line: string, y_labels: list<array{y: float, text: string}>, x_labels: list<array{x: float, text: string}>, columns: list<array{left: float, width: float, tip: array{label: string, total: int, parts: list<array{name: string, count: int, color: string}>}}>}
     */
    private function geometry(array $buckets, array $series): array
    {
        $width = 640.0;
        $height = 220.0;
        $left = 36.0;
        $right = 12.0;
        $top = 16.0;
        $bottom = 28.0;
        $plotW = $width - $left - $right;
        $plotH = $height - $top - $bottom;
        $n = max(1, count($buckets));
        $max = $this->niceMax(max(1, ...array_column($buckets, 'total')));
        $step = $n === 1 ? 0.0 : $plotW / ($n - 1);

        $x = function (int $index) use ($left, $step, $n, $plotW): float {
            if ($n === 1) {
                return $left + ($plotW / 2);
            }

            return $left + ($index * $step);
        };

        $y = function (float $value) use ($top, $plotH, $max): float {
            return $top + $plotH - (($value / $max) * $plotH);
        };

        $cumulative = array_fill(0, $n, 0.0);
        $areas = [];

        foreach ($series as $item) {
            $baseline = $cumulative;
            $topValues = [];
            foreach ($item['values'] as $index => $value) {
                $cumulative[$index] += $value;
                $topValues[$index] = $cumulative[$index];
            }
            $areas[] = [
                'd' => $this->areaPath($x, $y, $topValues, $baseline),
                'color' => $item['color'],
            ];
        }

        $line = '';
        foreach ($cumulative as $index => $value) {
            $line .= ($index === 0 ? 'M' : 'L').round($x($index), 1).' '.round($y($value), 1).' ';
        }

        $yLabels = [];
        foreach ([0, $max / 2, $max] as $tick) {
            $yLabels[] = [
                'y' => round($y((float) $tick), 1),
                'text' => (string) (int) round($tick),
            ];
        }

        $labelEvery = $n > 12 ? (int) ceil($n / 8) : 1;
        $xLabels = [];
        foreach ($buckets as $index => $bucket) {
            if ($index % $labelEvery !== 0 && $index !== $n - 1) {
                continue;
            }
            $xLabels[] = [
                'x' => round($x($index), 1),
                'text' => $bucket['label'],
            ];
        }

        $columnWidth = $n === 1 ? 12.0 : ($plotW / $n) / $width * 100;
        $columns = [];
        foreach ($buckets as $index => $bucket) {
            $center = $x($index) / $width * 100;
            $columns[] = [
                'left' => round($center - ($columnWidth / 2), 2),
                'width' => round($columnWidth, 2),
                'tip' => [
                    'label' => $bucket['label'],
                    'total' => $bucket['total'],
                    'parts' => $bucket['parts'],
                    'left' => round($center, 2),
                ],
            ];
        }

        return [
            'areas' => $areas,
            'line' => trim($line),
            'y_labels' => $yLabels,
            'x_labels' => $xLabels,
            'columns' => $columns,
        ];
    }

    /**
     * @param  callable(int): float  $x
     * @param  callable(float): float  $y
     * @param  list<float>  $topValues
     * @param  list<float>  $baseline
     */
    private function areaPath(callable $x, callable $y, array $topValues, array $baseline): string
    {
        $commands = [];
        foreach ($topValues as $index => $value) {
            $commands[] = ($index === 0 ? 'M' : 'L').round($x($index), 1).' '.round($y($value), 1);
        }
        for ($index = count($baseline) - 1; $index >= 0; $index--) {
            $commands[] = 'L'.round($x($index), 1).' '.round($y($baseline[$index]), 1);
        }
        $commands[] = 'Z';

        return implode(' ', $commands);
    }

    private function niceMax(int $max): int
    {
        if ($max <= 4) {
            return 4;
        }

        $power = 10 ** (int) floor(log10($max));
        $normalized = $max / $power;
        $nice = $normalized <= 1 ? 1 : ($normalized <= 2 ? 2 : ($normalized <= 5 ? 5 : 10));

        return (int) ($nice * $power);
    }

    /**
     * @param  Collection<int, object{ticket_type_id: int, aggregate: int|string}>  $waitingByType
     * @param  Collection<int|string, TicketType>  $types
     * @return array{total: int, items: list<array{name: string, prefix: string, waiting: int, percent: int, color: string}>}
     */
    private function queue(Collection $waitingByType, Collection $types, int $total): array
    {
        if ($total === 0) {
            return ['total' => 0, 'items' => []];
        }

        $items = [];
        $paletteIndex = 0;
        $assigned = 0;
        $rows = $waitingByType->sortByDesc(fn ($row): int => (int) $row->aggregate)->values();
        $lastIndex = $rows->count() - 1;

        foreach ($rows as $index => $row) {
            $type = $types->get((int) $row->ticket_type_id);
            $waiting = (int) $row->aggregate;
            if ($index === $lastIndex) {
                $percent = max(0, 100 - $assigned);
            } else {
                $percent = (int) round(($waiting / $total) * 100);
                $assigned += $percent;
            }

            $color = $type instanceof TicketType ? $this->colorForType($type) : null;
            if ($color === null) {
                $color = $this->palette($paletteIndex);
                $paletteIndex++;
            }

            $items[] = [
                'name' => $type instanceof TicketType ? $type->name : 'Tipo',
                'prefix' => $type instanceof TicketType ? (string) $type->prefix : '',
                'waiting' => $waiting,
                'percent' => $percent,
                'color' => $color,
            ];
        }

        return ['total' => $total, 'items' => $items];
    }

    /**
     * @param  list<int>  $unitIds
     * @return array{summary: array{active: int, total: int, percent: int|null, display: string}, rows: list<array{name: string, status: string, tone: string, ticket: string|null, elapsed: string|null}>}
     */
    private function desks(int $clinicId, array $unitIds): array
    {
        $desks = Desk::query()
            ->where('clinic_id', $clinicId)
            ->whereIn('unit_id', $unitIds)
            ->orderBy('name')
            ->get(['id', 'name', 'active', 'unit_id', 'clinic_id']);

        $total = $desks->count();
        $active = $desks->where('active', true)->count();

        $summary = [
            'active' => $active,
            'total' => $total,
            'percent' => $total === 0 ? null : (int) round(($active / $total) * 100),
            'display' => $total === 0 ? '—' : $active.' / '.$total,
        ];

        if ($total === 0) {
            return ['summary' => $summary, 'rows' => []];
        }

        $open = Ticket::query()
            ->where('clinic_id', $clinicId)
            ->whereIn('unit_id', $unitIds)
            ->whereIn('current_desk_id', $desks->pluck('id'))
            ->whereIn('status', [TicketStatus::CALLED, TicketStatus::IN_SERVICE])
            ->with('ticketType:id,name,prefix')
            ->get(['id', 'ticket_type_id', 'sequence_number', 'current_desk_id', 'status', 'called_at', 'service_started_at', 'clinic_id']);

        $byDesk = $open->groupBy('current_desk_id')->map(function (Collection $group): Ticket {
            return $group
                ->sortBy(fn (Ticket $ticket): int => $ticket->status === TicketStatus::IN_SERVICE ? 0 : 1)
                ->first();
        });

        $rows = [];
        foreach ($desks->take(8) as $desk) {
            $ticket = $byDesk->get($desk->id);
            if (! $desk->active) {
                $rows[] = [
                    'name' => $desk->name,
                    'status' => 'Inativa',
                    'tone' => 'inactive',
                    'ticket' => null,
                    'elapsed' => null,
                ];

                continue;
            }

            if (! $ticket instanceof Ticket) {
                $rows[] = [
                    'name' => $desk->name,
                    'status' => 'Disponível',
                    'tone' => 'available',
                    'ticket' => null,
                    'elapsed' => null,
                ];

                continue;
            }

            $anchor = $ticket->status === TicketStatus::IN_SERVICE && $ticket->service_started_at !== null
                ? $ticket->service_started_at
                : $ticket->called_at;

            $rows[] = [
                'name' => $desk->name,
                'status' => 'Atendendo',
                'tone' => 'busy',
                'ticket' => $ticket->display_code,
                'elapsed' => $anchor === null ? null : $this->formatElapsed($anchor),
            ];
        }

        return ['summary' => $summary, 'rows' => $rows];
    }

    /**
     * @param  list<int>  $unitIds
     * @return list<array{name: string, active: bool, status: string, updated: string}>
     */
    private function panels(int $clinicId, array $unitIds): array
    {
        return DisplayPanel::query()
            ->where('clinic_id', $clinicId)
            ->whereIn('unit_id', $unitIds)
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'active', 'updated_at', 'clinic_id', 'unit_id'])
            ->map(fn (DisplayPanel $panel): array => [
                'name' => $panel->name,
                'active' => (bool) $panel->active,
                'status' => $panel->active ? 'Ativo' : 'Inativo',
                'updated' => $panel->updated_at === null ? '—' : $this->ago($panel->updated_at),
            ])
            ->all();
    }

    /**
     * @param  list<int>  $unitIds
     * @return list<array{time: string, title: string, detail: string, tone: string}>
     */
    private function activity(int $clinicId, array $unitIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $events = collect();
        $now = CarbonImmutable::now((string) config('app.timezone'));

        $calls = TicketCall::query()
            ->where('clinic_id', $clinicId)
            ->whereIn('unit_id', $unitIds)
            ->whereBetween('called_at', [$from, $to])
            ->with([
                'ticket:id,ticket_type_id,sequence_number,clinic_id',
                'ticket.ticketType:id,name,prefix',
                'desk:id,name',
            ])
            ->orderByDesc('called_at')
            ->limit(8)
            ->get();

        foreach ($calls as $call) {
            $code = $call->ticket?->display_code ?? 'Senha';
            $desk = $call->desk?->name ?? 'mesa';
            $typeName = $call->ticket?->ticketType?->name;
            $recall = $call->call_type === TicketCallType::RECALL;
            $events->push([
                'at' => $call->called_at?->getTimestamp() ?? 0,
                'time' => $this->eventTime($call->called_at, $now),
                'title' => $recall ? "{$code} rechamada na {$desk}" : "{$code} chamada na {$desk}",
                'detail' => $typeName ? 'Senha '.$typeName : 'Chamada de senha',
                'tone' => $recall ? 'recall' : 'call',
            ]);
        }

        $started = Ticket::query()
            ->where('clinic_id', $clinicId)
            ->whereIn('unit_id', $unitIds)
            ->whereBetween('service_started_at', [$from, $to])
            ->with(['ticketType:id,name,prefix', 'currentDesk:id,name'])
            ->orderByDesc('service_started_at')
            ->limit(8)
            ->get(['id', 'ticket_type_id', 'sequence_number', 'current_desk_id', 'service_started_at', 'clinic_id']);

        foreach ($started as $ticket) {
            $events->push([
                'at' => $ticket->service_started_at?->getTimestamp() ?? 0,
                'time' => $this->eventTime($ticket->service_started_at, $now),
                'title' => $ticket->display_code.' atendimento iniciado',
                'detail' => $ticket->currentDesk?->name ?? 'Atendimento iniciado',
                'tone' => 'start',
            ]);
        }

        $finished = Ticket::query()
            ->where('clinic_id', $clinicId)
            ->whereIn('unit_id', $unitIds)
            ->where('status', TicketStatus::COMPLETED)
            ->whereBetween('completed_at', [$from, $to])
            ->with('ticketType:id,name,prefix')
            ->orderByDesc('completed_at')
            ->limit(8)
            ->get(['id', 'ticket_type_id', 'sequence_number', 'service_started_at', 'completed_at', 'clinic_id']);

        foreach ($finished as $ticket) {
            $detail = 'Atendimento finalizado';
            if ($ticket->service_started_at !== null && $ticket->completed_at !== null && $ticket->completed_at->greaterThanOrEqualTo($ticket->service_started_at)) {
                $seconds = $ticket->completed_at->getTimestamp() - $ticket->service_started_at->getTimestamp();
                $detail = 'Tempo de atendimento: '.$this->formatClock($seconds);
            }
            $events->push([
                'at' => $ticket->completed_at?->getTimestamp() ?? 0,
                'time' => $this->eventTime($ticket->completed_at, $now),
                'title' => $ticket->display_code.' atendimento finalizado',
                'detail' => $detail,
                'tone' => 'done',
            ]);
        }

        $noShows = Ticket::query()
            ->where('clinic_id', $clinicId)
            ->whereIn('unit_id', $unitIds)
            ->where('status', TicketStatus::NO_SHOW)
            ->whereBetween('completed_at', [$from, $to])
            ->with('ticketType:id,name,prefix')
            ->orderByDesc('completed_at')
            ->limit(8)
            ->get(['id', 'ticket_type_id', 'sequence_number', 'completed_at', 'clinic_id']);

        foreach ($noShows as $ticket) {
            $events->push([
                'at' => $ticket->completed_at?->getTimestamp() ?? 0,
                'time' => $this->eventTime($ticket->completed_at, $now),
                'title' => $ticket->display_code.' não compareceu',
                'detail' => 'Não comparecimento',
                'tone' => 'noshow',
            ]);
        }

        $transfers = TicketTransfer::query()
            ->where('clinic_id', $clinicId)
            ->whereIn('unit_id', $unitIds)
            ->whereBetween('transferred_at', [$from, $to])
            ->with([
                'ticket:id,ticket_type_id,sequence_number,clinic_id',
                'ticket.ticketType:id,name,prefix',
                'toDesk:id,name',
            ])
            ->orderByDesc('transferred_at')
            ->limit(8)
            ->get();

        foreach ($transfers as $transfer) {
            $code = $transfer->ticket?->display_code ?? 'Senha';
            $destination = $transfer->transfer_type === TicketTransferType::DESK
                ? ($transfer->toDesk?->name ?? 'outra mesa')
                : 'fila geral';
            $events->push([
                'at' => $transfer->transferred_at?->getTimestamp() ?? 0,
                'time' => $this->eventTime($transfer->transferred_at, $now),
                'title' => "{$code} transferida",
                'detail' => 'Destino: '.$destination,
                'tone' => 'transfer',
            ]);
        }

        return $events
            ->sortByDesc('at')
            ->take(8)
            ->map(fn (array $event): array => [
                'time' => $event['time'],
                'title' => $event['title'],
                'detail' => $event['detail'],
                'tone' => $event['tone'],
            ])
            ->values()
            ->all();
    }

    private function eventTime(?CarbonInterface $at, CarbonImmutable $now): string
    {
        if ($at === null) {
            return '—';
        }

        $local = $at->timezone((string) config('app.timezone'));

        return $local->isSameDay($now) ? $local->format('H:i') : $local->format('d/m H:i');
    }

    private function formatClock(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remain = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $remain);
        }

        return sprintf('%02d:%02d', $minutes, $remain);
    }

    private function formatElapsed(CarbonInterface $from): string
    {
        $seconds = max(0, CarbonImmutable::now((string) config('app.timezone'))->getTimestamp() - $from->getTimestamp());
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remain = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $remain);
    }

    private function ago(CarbonInterface $at): string
    {
        $seconds = max(0, CarbonImmutable::now((string) config('app.timezone'))->getTimestamp() - $at->getTimestamp());

        if ($seconds < 60) {
            return $seconds === 1 ? 'há 1 segundo' : "há {$seconds} segundos";
        }

        $minutes = intdiv($seconds, 60);
        if ($minutes < 60) {
            return $minutes === 1 ? 'há 1 minuto' : "há {$minutes} minutos";
        }

        $hours = intdiv($minutes, 60);
        if ($hours < 24) {
            return $hours === 1 ? 'há 1 hora' : "há {$hours} horas";
        }

        $days = intdiv($hours, 24);

        return $days === 1 ? 'há 1 dia' : "há {$days} dias";
    }

    private function deltaPercent(int $current, int $previous): ?int
    {
        if ($previous === 0) {
            return null;
        }

        return (int) round((($current - $previous) / $previous) * 100);
    }

    private function colorForType(TicketType $type): ?string
    {
        $haystack = mb_strtolower($type->name.' '.$type->prefix);

        if (str_contains($haystack, 'emerg') || str_contains($haystack, 'urgen')) {
            return '#dc2626';
        }

        if (str_contains($haystack, 'prefer') || str_contains($haystack, 'priorit')) {
            return '#16a34a';
        }

        if (str_contains($haystack, 'normal')) {
            return '#2563eb';
        }

        if ($type->priority >= 30) {
            return '#dc2626';
        }

        if ($type->priority >= 20) {
            return '#16a34a';
        }

        return null;
    }

    private function palette(int $index): string
    {
        $colors = ['#7c3aed', '#0891b2', '#d97706', '#0f766e', '#64748b'];

        return $colors[$index % count($colors)];
    }

    /**
     * @return array<string, mixed>
     */
    private function blank(
        User $user,
        string $period,
        string $periodLabel,
        string $comparisonLabel,
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $unitLabel,
    ): array {
        $granularity = $period === 'today' ? 'hour' : 'day';

        return [
            'period_key' => $period,
            'period_label' => $periodLabel,
            'comparison_label' => $comparisonLabel,
            'clinic_name' => (string) ($user->clinic?->name ?? 'Clínica'),
            'unit_label' => $unitLabel,
            'issued' => [
                'label' => $period === 'today' ? 'Senhas hoje' : 'Senhas no período',
                'value' => 0,
                'delta' => null,
            ],
            'waiting' => [
                'value' => 0,
                'hint' => 'Nenhuma senha aguardando.',
            ],
            'in_service' => [
                'value' => 0,
                'hint' => 'Nenhum atendimento em andamento.',
            ],
            'completed' => [
                'label' => $period === 'today' ? 'Finalizadas hoje' : 'Finalizadas no período',
                'value' => 0,
                'delta' => null,
            ],
            'wait' => [
                'seconds' => null,
                'samples' => 0,
                'display' => '—',
                'hint' => 'Sem chamadas no período.',
            ],
            'desks_summary' => [
                'active' => 0,
                'total' => 0,
                'percent' => null,
                'display' => '—',
            ],
            'chart' => $this->chart(collect(), collect(), $from, $to, $granularity),
            'queue' => ['total' => 0, 'items' => []],
            'desks' => [],
            'panels' => [],
            'activity' => [],
            'can_view_desks' => Gate::forUser($user)->allows('viewAny', Desk::class),
            'can_view_panels' => Gate::forUser($user)->allows('viewAny', DisplayPanel::class),
        ];
    }
}
