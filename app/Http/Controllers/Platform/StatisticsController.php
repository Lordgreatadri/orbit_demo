<?php

namespace App\Http\Controllers\Platform;

use App\Analytics\PlatformStatistics;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Platform statistics: growth, composition, adoption, and sign-in health across
 * all organisations. Aggregates only; no tenant operations and no member names.
 */
class StatisticsController extends Controller
{
    public function __invoke(Request $request, PlatformStatistics $statistics): View
    {
        return view('platform.statistics', [
            'user' => $request->user(),
            'stats' => $statistics->compose(),
        ]);
    }
}
