<?php

namespace App\Http\Controllers;

use App\Models\Obligation;
use App\Services\ComplianceStatusService;
use App\Support\CurrentLab;
use Illuminate\Http\Request;

class PortfolioController extends Controller
{
    public function __invoke(Request $request, ComplianceStatusService $status, CurrentLab $current)
    {
        $user = $request->user();
        $labs = $user->accessibleLabs();

        // Single-lab, non-super-admin users skip the portfolio and go straight in.
        if ($labs->count() === 1 && ! $user->isSuperAdmin()) {
            return redirect()->route('dashboard', $labs->first());
        }

        $cards = $labs->map(fn ($lab) => $current->run($lab, function () use ($lab, $status) {
            $counts = ['overdue' => 0, 'due_30' => 0, 'due_60' => 0, 'on_track' => 0, 'set_dates' => 0];
            foreach (Obligation::where('active', true)->get() as $o) {
                $counts[$status->for($o)['status']->value]++;
            }

            return ['lab' => $lab, 'counts' => $counts];
        }));

        return view('portfolio', ['cards' => $cards, 'user' => $user]);
    }
}
