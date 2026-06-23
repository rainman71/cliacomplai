<?php

namespace App\Http\Controllers;

use App\Models\Lab;

class UserManagementController extends Controller
{
    public function __invoke(Lab $lab)
    {
        return view('users', ['lab' => $lab]);
    }
}
