<?php

namespace App\Livewire;

use App\Models\Unit;
use App\Models\User;
use App\Services\AdminDashboardMetricsService;
use App\Services\OperationalContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class AdminDashboard extends Component
{
    public string $period = 'today';

    public string $customFrom = '';

    public string $customTo = '';

    public bool $customOpen = false;

    public string $unitKey = '';

    public string $errorMessage = '';

    public function mount(OperationalContext $context): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->hasPermission('dashboard.view'), 403);

        $today = now((string) config('app.timezone'))->toDateString();
        $this->customFrom = $today;
        $this->customTo = $today;

        $unit = $context->activeUnit($user, session());
        if ($unit instanceof Unit && $user->canOperateUnit($unit)) {
            $this->unitKey = (string) $unit->id;
        }
    }

    public function setPeriod(string $period): void
    {
        if (! in_array($period, ['today', '7d', '30d'], true)) {
            return;
        }

        $this->period = $period;
        $this->customOpen = false;
        $this->errorMessage = '';
        $this->resetErrorBag();
    }

    public function openCustom(): void
    {
        $this->customOpen = true;
        $this->resetErrorBag();
    }

    public function applyCustomPeriod(): void
    {
        $this->validate([
            'customFrom' => ['required', 'date'],
            'customTo' => ['required', 'date'],
        ]);

        $tz = (string) config('app.timezone');

        try {
            $from = CarbonImmutable::parse($this->customFrom, $tz)->startOfDay();
            $to = CarbonImmutable::parse($this->customTo, $tz)->startOfDay();
        } catch (\Throwable) {
            $this->addError('customFrom', 'Datas inválidas.');

            return;
        }

        if ($from->greaterThan($to)) {
            $this->addError('customFrom', 'A data inicial deve ser anterior ou igual à data final.');

            return;
        }

        $span = (int) floor(($to->getTimestamp() - $from->getTimestamp()) / 86400);
        if ($span > 90) {
            $this->addError('customFrom', 'O intervalo personalizado pode ter no máximo 90 dias.');

            return;
        }

        $this->period = 'custom';
        $this->errorMessage = '';
    }

    public function updatedUnitKey(mixed $value): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $value = $value === null ? '' : (string) $value;

        if ($value === '') {
            $this->unitKey = '';
            session()->forget(OperationalContext::UNIT_SESSION_KEY);

            return;
        }

        if (! ctype_digit($value)) {
            abort(403);
        }

        $unit = Unit::query()
            ->where('clinic_id', $user->clinic_id)
            ->whereKey((int) $value)
            ->first();

        if (! $unit instanceof Unit || ! $user->canOperateUnit($unit)) {
            abort(403);
        }

        app(OperationalContext::class)->setActiveUnit($user, $unit, session());
        $this->unitKey = (string) $unit->id;
    }

    public function render(AdminDashboardMetricsService $metrics): View
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->hasPermission('dashboard.view'), 403);

        $unitId = $this->unitKey === '' ? null : (int) $this->unitKey;

        try {
            $snapshot = $metrics->snapshot(
                $user,
                $this->period,
                $this->period === 'custom' ? $this->customFrom : null,
                $this->period === 'custom' ? $this->customTo : null,
                $unitId,
            );
        } catch (ValidationException $exception) {
            $this->errorMessage = collect($exception->errors())->flatten()->first() ?? 'Período inválido.';
            $this->period = 'today';
            $snapshot = $metrics->snapshot($user, 'today', null, null, $unitId);
        }

        return view('livewire.admin-dashboard', [
            'snapshot' => $snapshot,
            'units' => $metrics->unitsFor($user),
            'userName' => $user->name,
            'roleLabel' => $user->role?->label() ?? 'Usuário',
        ]);
    }
}
