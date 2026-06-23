<?php

namespace App\Http\Controllers;

use App\Models\Lab;

class DashboardController extends Controller
{
    public function __invoke(Lab $lab)
    {
        return view('dashboard', ['lab' => $lab]);
    }
}
