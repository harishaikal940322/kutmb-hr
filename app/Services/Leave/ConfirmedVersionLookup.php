<?php

namespace App\Services\Leave;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;

/** Technical lookup only; callers must separately establish authority, completeness and transaction safety. */
class ConfirmedVersionLookup
{
    public function workingScheduleFor(string $date): stdClass
    {
        $policy = $this->oneFor('leave_policy_versions', $date, 'schedule_simulated_at', 'schedule_simulated_by');
        try {
            $weekdays = $policy->working_weekdays === null ? null : json_decode($policy->working_weekdays, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException('Confirmed working schedule is incomplete.');
        }
        if (! is_array($weekdays) || ! array_is_list($weekdays) || $weekdays === [] || count(array_unique($weekdays, SORT_REGULAR)) !== count($weekdays)) {
            throw new RuntimeException('Confirmed working schedule is incomplete.');
        }
        foreach ($weekdays as $day) {
            if (! is_int($day) || $day < 1 || $day > 7) {
                throw new RuntimeException('Confirmed working schedule is incomplete.');
            }
        }
        foreach (['work_start', 'session_boundary', 'work_end'] as $field) {
            if (! is_string($policy->{$field}) || preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]\z/D', $policy->{$field}) !== 1) {
                throw new RuntimeException('Confirmed working schedule is incomplete.');
            }
        }
        if ($policy->work_start >= $policy->session_boundary || $policy->session_boundary >= $policy->work_end) {
            throw new RuntimeException('Confirmed working schedule is incomplete.');
        }
        $policy->working_weekdays = $weekdays;

        return $policy;
    }

    /** @return array{version: stdClass, holidays: list<string>} */
    public function calendarFor(string $date): array
    {
        $version = $this->oneFor('leave_calendar_versions', $date);
        $days = DB::table('leave_calendar_days')->where('calendar_version_id', $version->id)->get();
        $active = [];
        foreach ($days as $day) {
            if (($day->cancelled_at === null) !== ($day->cancelled_by === null)) {
                throw new RuntimeException('Confirmed calendar is inconsistent.');
            }
            if ($day->cancelled_at === null) {
                if (! in_array($day->kind, ['holiday', 'replacement'], true)
                    || $day->date < $version->effective_from || ($version->effective_to !== null && $day->date > $version->effective_to)) {
                    throw new RuntimeException('Confirmed calendar is inconsistent.');
                }
                $active[(int) $day->id] = $day;
            }
        }
        foreach ($active as $day) {
            if ($day->kind === 'holiday' && $day->original_day_id !== null) {
                throw new RuntimeException('Confirmed calendar is inconsistent.');
            }
            if ($day->kind === 'replacement') {
                $original = $active[(int) $day->original_day_id] ?? null;
                if ($original === null || $original->kind !== 'holiday' || $original->date === $day->date) {
                    throw new RuntimeException('Confirmed calendar is inconsistent.');
                }
            }
        }

        return ['version' => $version, 'holidays' => array_values(array_map(fn ($day): string => $day->date, $active))];
    }

    private function oneFor(string $table, string $date, string $markerAt = 'confirmed_at', string $markerBy = 'confirmed_by'): stdClass
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Technical version lookup is unavailable.');
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Asia/Kuala_Lumpur'));
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new RuntimeException('A valid date is required.');
        }
        $versions = DB::table($table)->where('effective_from', '<=', $date)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $date))->get();
        $confirmed = [];
        foreach ($versions as $version) {
            if (($version->{$markerAt} === null) !== ($version->{$markerBy} === null)
                || ($table === 'leave_policy_versions' && ((($version->confirmed_at === null) !== ($version->confirmed_by === null)) || ($version->confirmed_at !== null && $version->cancelled_at !== null)))
                || ($version->cancelled_at === null) !== ($version->cancelled_by === null)
                || ($version->{$markerAt} !== null && $version->cancelled_at !== null)) {
                throw new RuntimeException('Version state is inconsistent.');
            }
            if ($version->{$markerAt} !== null) {
                $confirmed[] = $version;
            }
        }
        if (count($confirmed) !== 1) {
            throw new RuntimeException('Exactly one confirmed version is required.');
        }

        return $confirmed[0];
    }
}
