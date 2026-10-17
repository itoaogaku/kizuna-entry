<?php

namespace App\Http\Controllers;

use App\Mail\PressReceivedMail;
use App\Models\Meet;
use App\Models\PressRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 取材・撮影のお申込み（プレス申込）。
 */
class PressController extends Controller
{
    public function create(Meet $meet): View
    {
        abort_unless($meet->is_published && $meet->acceptsPressRequests(), 404);

        return view('site.press', ['meet' => $meet]);
    }

    public function store(Request $request, Meet $meet): RedirectResponse
    {
        abort_unless($meet->is_published && $meet->acceptsPressRequests(), 404);

        $data = $request->validate([
            'media_name' => ['required', 'string', 'max:100'],
            'contact_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'coverage' => ['required', Rule::in(PressRequest::COVERAGES)],
            'people' => ['required', 'integer', 'min:1', 'max:30'],
            'purpose' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'agree' => ['accepted'],
        ], [], [
            'media_name' => '媒体名・会社名', 'contact_name' => '担当者氏名', 'coverage' => '取材の内容',
            'people' => '来場人数', 'purpose' => '掲載・使用予定', 'notes' => '備考', 'agree' => '注意事項への同意',
        ]);
        unset($data['agree']);

        $press = $meet->pressRequests()->create($data);
        $mail = Mail::to($press->email);
        if ($admin = config('services.kizuna.admin_email')) {
            $mail->bcc($admin);
        }
        $mail->send(new PressReceivedMail($press));

        return redirect()->route('site.meets')
            ->with('status', '取材・撮影のお申込みを受け付けました。確認のメールをお送りしました。当日の詳細は運営からご連絡します。');
    }
}
