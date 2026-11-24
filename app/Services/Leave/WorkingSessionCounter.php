<?php

namespace App\Services\Leave;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Counts supplied half-day slots only; callers must establish policy and calendar completeness. */
class WorkingSessionCounter
{
    /**
     * @param  list<array{date: string, session: string}>  $slots
     * @param  list<mixed>|null  $workingWeekdays  ISO weekday numbers, explicitly supplied
     * @param  list<string>|null  $holidays  Explicit complete calendar dates for the requested range
     */
    public function countUnits(array $slots, ?array $workingWeekdays, ?array $holidays): int
    {
        if ($workingWeekdays === null || $workingWeekdays === [] || $holidays === null) {
            throw new InvalidArgumentException('A complete working schedule and calendar are required.');
        }
        foreach ($workingWeekdays as $weekday) {
            if (! is_int($weekday) || $weekday < 1 || $weekday > 7) {
                throw new InvalidArgumentException('Working weekdays must be ISO weekday numbers.');
            }
        }
        if (count(array_unique($workingWeekdays)) !== count($workingWeekdays)) {
            throw new InvalidArgumentException('Working weekdays must be unique.');
        }
        foreach ($holidays as $date) {
            $this->parseDate($date);
        }
        $seen = [];
        $units = 0;
        foreach ($slots as $slot) {
            $date = $this->parseDate($slot['date']);
            if (! in_array($slot['session'], ['AM', 'PM'], true)) {
                throw new InvalidArgumentException('A valid half-day session is required.');
            }
            $key = $slot['date'].':'.$slot['session'];
            if (isset($seen[$key])) {
                throw new InvalidArgumentException('A half-day session cannot be counted twice.');
            }
            $seen[$key] = true;
            if (in_array((int) $date->format('N'), $workingWeekdays, true) && ! in_array($slot['date'], $holidays, true)) {
                $units++;
            }
        }

        return $units;
    }

    private function parseDate(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Kuala_Lumpur'));
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('A valid calendar date is required.');
        }

        return $date;
    }
}
