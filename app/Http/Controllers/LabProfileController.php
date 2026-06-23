<?php

namespace App\Http\Controllers;

use App\Models\Lab;

class LabProfileController extends Controller
{
    // Invokable controllers under /labs/{lab} MUST take Lab $lab first or Laravel 404s the dispatch.
    public function __invoke(Lab $lab)
    {
        return view('labs.profile', ['lab' => $lab]);
    }
}
