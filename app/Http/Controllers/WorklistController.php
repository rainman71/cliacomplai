<?php

namespace App\Http\Controllers;

use App\Services\ComplianceStatusService;
use App\Support\CurrentLab;
use Illuminate\Http\Request;

/**
 * One merged, prioritized list of every overdue obligation across all of the user's labs —
 * the multi-lab director's "what do I deal with first" view. Reuses the executive roll-up
 * builder and flattens it into a single days-overdue-sorted queue.
 */
class WorklistController extends Controller
{
    public function __invoke(Request $request, ComplianceStatusService $status, CurrentLab $current)
    {
        $user = $request->user();
        $labs = $user->accessibleLabs();

        // Only meaningful for people who span multiple labs (same gate as the executive report).
        if ($labs->count() < 2 && ! $user->isSuperAdmin()) {
            return redirect()->route('portfolio');
        }

        $items = ExecutiveReportController::build($labs, $status, $current)
            ->flatMap(fn ($entry) => $entry['overdue']->map(fn ($r) => [
                'lab' => $entry['lab'],
                'o' => $r['o'],
                'days' => $r['days'],
            ]))
            ->sortBy('days') // most negative (most overdue) first
            ->values();

        return view('worklist', ['items' => $items, 'user' => $user]);
    }
}
