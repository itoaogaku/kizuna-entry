<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@hasSection('title')@yield('title') | @endif運営管理画面</title>
    <meta name="theme-color" content="#8f1d1d">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700;900&family=Oswald:wght@500;600&display=swap">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=21">
</head>
<body class="admin">
{{-- 運営専用。申込者向けのサイトとは見た目も導線も分ける --}}
<header class="admin-header">
    <div class="inner">
        <a class="admin-brand" href="{{ route('admin.meets.index') }}">
            <span class="admin-badge">運営専用</span>
            <span>{{ config('kizuna.site.name') }} 管理画面</span>
        </a>
        <nav>
            <a href="{{ route('admin.meets.index') }}" @class(['current' => request()->routeIs('admin.meets.index', 'admin.meets.show', 'admin.entries.*', 'admin.documents.*', 'admin.roster.*', 'admin.press.*')])>大会一覧</a>
            <a href="{{ route('admin.meets.create') }}" @class(['current' => request()->routeIs('admin.meets.create', 'admin.meets.edit')])>新規大会登録</a>
            <a href="{{ route('admin.teams.index') }}" @class(['current' => request()->routeIs('admin.teams.*')])>登録団体・個人</a>
            <a href="{{ route('site.meets') }}" target="_blank" rel="noopener">公開サイトを確認 ↗</a>
            <span class="who">{{ auth()->user()->displayName() }}</span>
            <form method="POST" action="{{ route('logout') }}" class="inline-form">
                @csrf
                <button class="btn small" type="submit">ログアウト</button>
            </form>
        </nav>
    </div>
</header>
<div class="admin-strip">この画面は運営者だけが使う管理画面です。申込者には表示されません。</div>
<main>
    @if (session('status'))
        <div class="flash ok">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="flash err">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @yield('content')
</main>
@stack('scripts')
</body>
</html>
