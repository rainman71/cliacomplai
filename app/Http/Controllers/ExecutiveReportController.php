<?php

namespace App\Http\Controllers;

use App\Models\Obligation;
use App\Services\ComplianceStatusService;
use App\Support\CurrentLab;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ExecutiveReportController extends Controller
{
    public function __invoke(Request $request, ComplianceStatusService $status, CurrentLab $current)
    {
        $user = $request->user();
        $labs = $user->accessibleLabs();

        // Only meaningful for people who span multiple labs.
        if ($labs->count() < 2 && ! $user->isSuperAdmin()) {
            return redirect()->route('portfolio');
        }

        $report = self::build($labs, $status, $current);

        return view('executive', ['report' => $report, 'user' => $user]);
    }

    /** @return Collection<int, array{lab: \App\Models\Lab, counts: array, overdue: Collection}> */
    public static function build(Collection $labs, ComplianceStatusService $status, CurrentLab $current): Collection
    {
        return $labs->map(fn ($lab) => $current->run($lab, function () use ($lab, $status) {
            $overdue = collect();
            $counts = ['overdue' => 0, 'due_soon' => 0, 'on_track' => 0, 'set_dates' => 0];

            foreach (Obligation::where('active', true)->get() as $o) {
                $d = $status->for($o);
                $s = $d['status']->value;
                if ($s === 'overdue') {
                    $counts['overdue']++;
                    $overdue->push(['o' => $o, 'days' => $d['days_until_due']]);
                } elseif ($s === 'due_30' || $s === 'due_60') {
                    $counts['due_soon']++;
                } elseif ($s === 'on_track') {
                    $counts['on_track']++;
                } else {
                    $counts['set_dates']++;
                }
            }

            return ['lab' => $lab, 'counts' => $counts, 'overdue' => $overdue->sortBy('days')->values()];
        }));
    }
}
