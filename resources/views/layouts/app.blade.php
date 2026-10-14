<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@hasSection('title')@yield('title') | @endif{{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=1">
</head>
<body>
<header class="site">
    <div class="inner">
        <a class="brand" href="{{ route('home') }}">{{ config('app.name') }}</a>
        <nav>
            @auth
                <a href="{{ route('athletes.index') }}">選手登録</a>
                <a href="{{ route('entries.index') }}">エントリー履歴</a>
                @if (auth()->user()->is_admin)
                    <a href="{{ route('admin.meets.index') }}">管理画面</a>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="inline-form">
                    @csrf
                    <button class="btn secondary small" type="submit">ログアウト</button>
                </form>
            @else
                <a href="{{ route('login') }}">ログイン</a>
                <a class="btn small" href="{{ route('register') }}">チーム登録</a>
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
