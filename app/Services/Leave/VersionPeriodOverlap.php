<?php

namespace App\Services\Leave;

use Illuminate\Support\Collection;

class VersionPeriodOverlap
{
    public function overlaps(string $start, ?string $end, string $otherStart, ?string $otherEnd): bool
    {
        return ($end === null || $otherStart <= $end)
            && ($otherEnd === null || $start <= $otherEnd);
    }

    /** @param Collection<int, \stdClass> $versions
     * @return Collection<int, \stdClass>
     */
    public function annotate(Collection $versions): Collection
    {
        return $versions->map(function (\stdClass $version) use ($versions): \stdClass {
            $version->overlapping_ids = $versions->filter(fn (\stdClass $other): bool => $other->id !== $version->id
                && $this->overlaps($version->effective_from, $version->effective_to, $other->effective_from, $other->effective_to))
                ->pluck('id')->values()->all();

            return $version;
        });
    }
}
