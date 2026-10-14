<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\AthleteRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * 個人申し込み：本人の選手情報ごと登録し、メールアドレス＋パスワードでログインする。
 */
class IndividualAuthController extends Controller
{
    public function loginForm(): View
    {
        return view('auth.individual-login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials + ['type' => User::TYPE_INDIVIDUAL], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'メールアドレスまたはパスワードが違います。']);
        }
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function registerForm(): View
    {
        return view('auth.individual-register');
    }

    public function register(Request $request): RedirectResponse
    {
        $input = AthleteRules::normalize($request->all());
        $data = validator($input, [
            'phone' => ['required', 'string', 'max:20'],
            'team_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'agree' => ['accepted'],
        ] + AthleteRules::rules())->validate();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'type' => User::TYPE_INDIVIDUAL,
                'name' => $data['last_name'].' '.$data['first_name'],
                'team_name' => $data['team_name'] ?? null,
                'phone' => $data['phone'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);
            $user->athletes()->create(collect($data)->only(array_keys(AthleteRules::rules()))->all());

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home')->with('status', '登録しました。出場する大会を選んでください。次回からはメールアドレスとパスワードでログインできます。');
    }
}
