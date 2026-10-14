<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('kizuna:make-admin {email : 運営者にするアカウントのメールアドレス}')]
#[Description('登録済みのアカウントに運営者（管理画面）の権限を付ける')]
class MakeAdmin extends Command
{
    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('そのメールアドレスのアカウントはありません。先に /team/register で登録してください。');

            return self::FAILURE;
        }
        $user->forceFill(['is_admin' => true])->save();
        $this->info($user->email.' を運営者にしました。');

        return self::SUCCESS;
    }
}
