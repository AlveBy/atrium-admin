<?php

declare(strict_types=1);

namespace Atrium\Admin\Fields;

class DateTimeField extends DateField
{
    protected string $format = 'Y-m-d H:i:s';

    protected string $displayFormat = 'd.m.Y H:i';

    protected bool $showSeconds = false;

    protected int $minuteStep = 5;

    public function getType(): string
    {
        return 'datetime';
    }

    /**
     * Show seconds input
     */
    public function withSeconds(bool $show = true): static
    {
        $this->showSeconds = $show;
        if ($show) {
            $this->displayFormat = 'd.m.Y H:i:s';
        }

        return $this;
    }

    /**
     * Set minute step
     */
    public function minuteStep(int $step): static
    {
        $this->minuteStep = $step;

        return $this;
    }

    protected function getFieldProps(): array
    {
        return array_merge(parent::getFieldProps(), [
            'showSeconds' => $this->showSeconds,
            'minuteStep' => $this->minuteStep,
        ]);
    }
}
