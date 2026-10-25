<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class PublicPageController extends Controller
{
    public function home(): View
    {
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
