<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PumkAdminDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('pumk-admin.dashboard');
    }
}
