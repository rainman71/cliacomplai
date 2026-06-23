<?php

namespace App\Http\Controllers;

use App\Models\Lab;

class Cms209Controller extends Controller
{
    // Invokable controllers under /labs/{lab} MUST take Lab $lab first or Laravel 404s the dispatch.
    public function __invoke(Lab $lab)
    {
        return view('forms.cms-209', ['lab' => $lab]);
    }
}
