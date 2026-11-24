<?php

namespace App\Services\Leave;

use RuntimeException;

/** Local synthetic simulation only; no balance, approval or submission is performed. */
class TechnicalSessionSimulation
{
    public function __construct(
        private readonly ConfirmedVersionLookup $versions,
        private readonly WorkingSessionCounter $counter,
    ) {}

    /** @param list<array{date: string, session: string}> $slots */
    public function countUnits(array $slots): int
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Technical simulation is unavailable.');
        }
        $byDate = [];
        foreach ($slots as $slot) {
            $byDate[$slot['date']][] = $slot;
        }
        $total = 0;
        foreach ($byDate as $date => $dateSlots) {
            $schedule = $this->versions->workingScheduleFor($date);
            $calendar = $this->versions->calendarFor($date);
            $total += $this->counter->countUnits($dateSlots, $schedule->working_weekdays, $calendar['holidays']);
        }

        return $total;
    }
}
