<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Workshop\Workshop;
use Illuminate\Contracts\View\View;

class LandingController extends Controller
{
    public function index(): View
    {
        // PHASE 6: was ExperienceCatalogue::all(). Workshop::menu() is the
        // cached active list, ordered shortest session first, so the page still
        // costs no query on a warm cache.
        return view('public.landing', [
            'experiences' => Workshop::menu(),
        ]);
    }
}
