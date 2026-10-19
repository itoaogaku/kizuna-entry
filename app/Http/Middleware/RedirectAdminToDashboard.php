<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 運営アカウントは管理画面専用。選手登録やエントリーの画面は開かず、管理画面へ戻す。
 */
class RedirectAdminToDashboard
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_admin) {
            return redirect()->route('admin.meets.index')
                ->with('status', '運営アカウントではエントリーの画面は使えません。申込みの内容は管理画面で確認してください。');
        }

        return $next($request);
    }
}
