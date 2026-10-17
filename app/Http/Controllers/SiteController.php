<?php

namespace App\Http\Controllers;

use App\Models\Meet;
use App\Services\GradeAdvance;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 公開サイトの「大会開催」ページ。年度ごとの開催予定と、各大会の資料・エントリーへの入口。
 */
class SiteController extends Controller
{
    public function meets(Request $request): View
    {
        $published = Meet::with('events')->where('is_published', true)->orderBy('held_on')->get();
        $years = $published->map->fiscalYear()->unique()->sortDesc()->values();
        $current = GradeAdvance::fiscalYear();
        $year = $request->integer('year') ?: ($years->contains($current) ? $current : ($years->first() ?? $current));

        $meets = $published->filter(fn (Meet $m) => $m->fiscalYear() === $year)->values();

        return view('site.meets', [
            'year' => $year,
            'years' => $years,
            'meets' => $meets,
            // 大会ごとの詳細は新しい順（今のサイトと同じ並び）
            'details' => $meets->sortByDesc('held_on')->values(),
            'next' => $published->first(fn (Meet $m) => $m->stage() !== 'finished'),
        ]);
    }
}
