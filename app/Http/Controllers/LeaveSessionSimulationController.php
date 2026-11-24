<?php

namespace App\Http\Controllers;

use App\Services\Leave\TechnicalSessionSimulation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class LeaveSessionSimulationController extends Controller
{
    public function __invoke(Request $request, TechnicalSessionSimulation $simulation): Response
    {
        $data = $request->validate([
            'date' => ['nullable', 'required_with:sessions', 'date_format:Y-m-d'],
            'sessions' => ['nullable', 'required_with:date', 'array', 'list', 'min:1', 'max:2'],
            'sessions.*' => ['string', 'distinct', Rule::in(['AM', 'PM'])],
        ]);
        $units = null;
        $unavailable = false;
        if (isset($data['date'], $data['sessions'])) {
            /** @var list<string> $sessions */
            $sessions = array_values($data['sessions']);
            $date = (string) $data['date'];
            $slots = array_map(fn (string $session): array => ['date' => $date, 'session' => $session], $sessions);
            try {
                $units = $simulation->countUnits($slots);
            } catch (RuntimeException) {
                $unavailable = true;
            }
        }

        return Inertia::render('leave/SessionSimulation', [
            'date' => $data['date'] ?? '', 'sessions' => $data['sessions'] ?? [], 'units' => $units, 'unavailable' => $unavailable,
        ]);
    }
}
