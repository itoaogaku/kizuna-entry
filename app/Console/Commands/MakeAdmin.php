<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('kizuna:make-admin {email? : 運営者にするアカウントのメールアドレス（省略時は KIZUNA_ADMIN_ACCOUNTS）} {--if-exists : アカウントがまだなければ何もしない}')]
#[Description('登録済みのアカウントに運営者（管理画面）の権限を付ける')]
class MakeAdmin extends Command
{
    public function handle(): int
    {
        $emails = $this->argument('email')
            ? [$this->argument('email')]
            : array_filter(array_map('trim', explode(',', (string) config('services.kizuna.admin_accounts'))));

        foreach ($emails as $email) {
            $user = User::where('email', $email)->first();
            if (! $user) {
                if ($this->option('if-exists')) {
                    $this->line($email.' はまだ登録されていないので飛ばしました。');

                    continue;
                }
                $this->error('そのメールアドレスのアカウントはありません。先に /team/register で登録してください。');

                return self::FAILURE;
            }
            $user->forceFill(['is_admin' => true])->save();
            $this->info($user->email.' を運営者にしました。');
        }

        return self::SUCCESS;
    }
}
