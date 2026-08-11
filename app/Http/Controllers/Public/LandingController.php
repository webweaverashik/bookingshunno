<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\ExperienceCatalogue;
use Illuminate\Contracts\View\View;

class LandingController extends Controller
{
    public function index(): View
    {
        return view('public.landing', [
            'experiences' => ExperienceCatalogue::all(),
        ]);
    }
}
