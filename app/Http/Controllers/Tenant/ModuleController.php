<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Labelled "coming soon" pages for modules planned in later stages. The page
 * is permission-gated like the real module will be, so navigation and access
 * behave consistently before and after delivery.
 */
class ModuleController extends Controller
{
    public function show(Request $request, string $module): View
    {
        $config = config("orbit.modules.{$module}");

        abort_if($config === null, 404);
        abort_unless($request->user()->can($config['permission']), 403);

        return view('tenant.modules.show', [
            'module' => $config + ['key' => $module],
        ]);
    }
}
