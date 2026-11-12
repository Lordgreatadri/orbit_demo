<?php

namespace App\Http\Controllers\Tenant;

use App\Analytics\DashboardComposer;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Organisation, department, or personal overview. Figures come from
 * DashboardComposer, which does not load another tenant's rows.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardComposer $composer): View
    {
        return view('tenant.dashboard', $composer->compose($request->user()));
    }
}
