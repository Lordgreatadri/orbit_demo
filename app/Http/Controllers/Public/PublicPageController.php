<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PublicPageController extends Controller
{
    public function home(Request $request, TenantContext $context): View|RedirectResponse
    {
        if ($context->has()) {
            return $request->user()
                ? redirect()->route('org.dashboard')
                : redirect()->route('login');
        }

        return view('public.home');
    }

    public function about(): View
    {
        return view('public.about');
    }

    public function guide(): View
    {
        return view('public.guide');
    }

    public function terms(): View
    {
        return view('public.terms');
    }

    public function privacy(): View
    {
        return view('public.privacy');
    }
}
