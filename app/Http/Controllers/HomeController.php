<?php

namespace App\Http\Controllers;

use App\Models\Meet;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $meets = Meet::where('is_published', true)
            ->whereDate('held_on', '>=', today())
            ->orderBy('held_on')
            ->get();

        return view('home', [
            'meets' => $meets,
            'user' => $request->user(),
        ]);
    }
}
