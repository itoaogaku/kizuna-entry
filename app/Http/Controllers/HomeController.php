<?php

namespace App\Http\Controllers;

use App\Models\Meet;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * エントリーの入口。ログインしていてもいなくても、まず Web とエクセル添付のどちらで申し込むかを選ぶ。
     */
    public function __invoke(Request $request): View
    {
        return view('start', [
            'meets' => $this->meets(),
            'user' => $request->user(),
        ]);
    }

    /**
     * Webエントリー（ログイン後）：申込データと受付中の大会の一覧。
     */
    public function web(Request $request): View
    {
        return view('home', [
            'meets' => $this->meets(),
            'user' => $request->user(),
        ]);
    }

    private function meets(): Collection
    {
        return Meet::where('is_published', true)
            ->whereDate('held_on', '>=', today())
            ->orderBy('held_on')
            ->get();
    }
}
