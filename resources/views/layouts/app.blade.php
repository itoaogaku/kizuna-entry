<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@hasSection('title')@yield('title') | @endif{{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=2">
</head>
<body>
<header class="site">
    <div class="inner">
        <a class="brand" href="{{ route('home') }}">{{ config('app.name') }}</a>
        <nav>
            @auth
                <span class="muted">{{ auth()->user()->displayName() }}（{{ auth()->user()->typeLabel() }}）</span>
                <a href="{{ route('athletes.index') }}">{{ auth()->user()->isTeam() ? '選手登録' : '登録情報' }}</a>
                <a href="{{ route('entries.index') }}">エントリー履歴</a>
                @if (auth()->user()->is_admin)
                    <a href="{{ route('admin.meets.index') }}">管理画面</a>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="inline-form">
                    @csrf
                    <button class="btn secondary small" type="submit">ログアウト</button>
                </form>
            @else
                <a href="{{ route('team.login') }}">団体申し込み</a>
                <a href="{{ route('individual.login') }}">個人申し込み</a>
            @endauth
        </nav>
    </div>
</header>
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
