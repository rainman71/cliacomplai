<?php

namespace App\Http\Controllers;

class LabManagementController extends Controller
{
    public function __invoke()
    {
        return view('admin.labs');
    }
}
