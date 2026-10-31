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

        $permission = $config['permission'];
        $allowed = str_starts_with((string) $permission, 'platform.')
            ? $request->user()->can($permission)
            : $request->user()->can('platform.access');
        abort_unless($allowed, 403);

        return view('platform.modules.show', [
            'module' => $config + ['key' => $module],
        ]);
    }
}
