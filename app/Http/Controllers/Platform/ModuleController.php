<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    public function show(Request $request, string $module): View
    {
        $config = config("orbit.modules.{$module}");

        abort_if($config === null, 404);
        abort_unless($request->user()->can($config['permission']), 403);

        return view('platform.modules.show', [
            'module' => $config + ['key' => $module],
        ]);
    }
}
