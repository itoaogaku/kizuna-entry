<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@hasSection('title')@yield('title') | @endif{{ config('app.name') }}</title>
    <meta name="theme-color" content="#265c64">
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700;900&family=Oswald:wght@500;600&display=swap">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=17">
</head>
<body>
<header class="site">
    <div class="inner">
        <a class="brand" href="{{ route('site.meets') }}">
            <img src="{{ asset('images/logo.png') }}" alt="" width="40" height="35">
            <span>アスリートキャリアセンター<small>{{ config('app.name') }}</small></span>
        </a>
        <nav>
            <a href="{{ route('site.meets') }}" @class(['current' => request()->routeIs('site.meets')])>大会開催</a>
            <a href="{{ route('home') }}" @class(['current' => request()->routeIs('home')])>エントリー</a>
            @auth
                <span class="muted who">{{ auth()->user()->displayName() }}（{{ auth()->user()->typeLabel() }}）</span>
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
                <a href="{{ route('team.login') }}">ログイン</a>
            @endauth
        </nav>
    </div>
</header>
@yield('hero')
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
<footer class="site-footer">
    @php $org = config('kizuna.organization'); @endphp
    <div class="inner">
        <div class="footer-org">
            <a class="brand" href="{{ $org['home_url'] }}">
                <img src="{{ asset('images/logo.png') }}" alt="" width="36" height="31">
                <span>{{ $org['name'] }}</span>
            </a>
            <address>
                〒{{ $org['postal_code'] }}<br>{{ $org['address'] }}<br>
                Fax：{{ $org['fax'] }}<br>
                <a href="mailto:{{ $org['email'] }}">{{ $org['email'] }}</a>
            </address>
        </div>
        <nav aria-label="サイト内リンク">
            <a href="{{ $org['home_url'] }}">ホーム</a>
            <a href="{{ route('site.meets') }}">大会開催</a>
            <a href="{{ route('home') }}">エントリー</a>
            <a href="{{ route('sheet.create') }}">エクセル添付で申し込む</a>
        </nav>
        <p class="copy">&copy; {{ date('Y') }} {{ $org['name'] }}</p>
    </div>
</footer>
@stack('scripts')
</body>
</html>
