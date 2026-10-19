<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * 団体申し込み：団体名＋パスワードでログインし、団体の申込データを開く。
 */
class TeamAuthController extends Controller
{
    public function loginForm(): View
    {
        return view('auth.team-login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'team_name' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::findTeamByName($request->input('team_name'));
        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages(['team_name' => '団体名またはパスワードが違います。']);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        if ($request->user()->is_admin) {
            return redirect()->route('admin.meets.index');
        }

        return redirect()->intended(route('home'));
    }

    public function registerForm(): View
    {
        return view('auth.team-register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'team_name' => ['required', 'string', 'max:100'],
            'team_kana' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'agree' => ['accepted'],
        ]);

        if (User::findTeamByName($data['team_name'])) {
            throw ValidationException::withMessages([
                'team_name' => 'この団体名はすでに登録されています。登録済みの場合は「団体申し込み」からログインしてください。同じ名前の別団体の場合は、地域名などを付けて区別してください。',
            ]);
        }

        $user = User::create($data + ['type' => User::TYPE_TEAM]);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('athletes.index')->with('status', '団体を登録しました。続けて所属選手を登録してください。次回からは団体名とパスワードでログインできます。');
    }
}
