<?php

namespace App\Http\Controllers;

use App\Models\Meet;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * ログイン前：団体申し込みか個人申し込みかを選ぶ最初の画面。
     * ログイン後：受付中の大会の一覧。
     */
    public function __invoke(Request $request): View
    {
        $meets = Meet::where('is_published', true)
            ->whereDate('held_on', '>=', today())
            ->orderBy('held_on')
            ->get();

        if (! $request->user()) {
            return view('start', compact('meets'));
        }

        return view('home', [
            'meets' => $meets,
            'user' => $request->user(),
        ]);
    }
}
