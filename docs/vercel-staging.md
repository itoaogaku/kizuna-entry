# Vercel（テスト環境）の作り方

本番（Xserver）に出す前に、Vercel で動作を確かめるための手順です。
テスト環境では Stripe・KOMOJU を **テストモード** で使うので、実際のお金は動きません。

```
作業ブランチに push ──▶ Vercel（テスト環境）に自動で反映
        │  確認OK →「mainを反映して」
        ▼
main にマージ ──▶ GitHub Actions が Xserver（本番）に自動でアップロード
```

> Vercel の無料プラン（Hobby）は個人・非商用向けです。会社の業務として使う場合は Pro プランが必要になる点にご注意ください。

## 1. プロジェクトを作る

1. https://vercel.com に GitHub アカウントでサインアップする。
2. `Add New… → Project` で `itoaogaku/kizuna-entry` を選ぶ（リポジトリが出なければ「Adjust GitHub App Permissions」で許可する）。
3. **Framework Preset は「Other」**、Build Command・Output Directory は空のまま。
4. まだ **Deploy は押さず**、先に 2〜3 の設定をする（押してしまっても、設定後に Redeploy すれば大丈夫です）。

## 2. データベース（Neon）をつなぐ

1. プロジェクトの `Storage → Create Database → Neon（Serverless Postgres）` を選ぶ。
2. リージョンは東京に近いもの（例：Singapore）を選び、このプロジェクトに Connect する。
   環境変数 `DATABASE_URL` が自動で入ります。表はデプロイのたびに自動で作られます（`php artisan migrate`）。

## 3. 環境変数

`Settings → Environment Variables` に追加します（Environment はすべてにチェック）。

| 名前 | 値 |
|---|---|
| `APP_KEY` | `base64:` で始まる32バイトの鍵。Mac のターミナルで `echo "base64:$(openssl rand -base64 32)"` を実行して出た文字列 |
| `APP_ENV` | `staging` |
| `APP_DEBUG` | `false` |
| `APP_URL` | テスト環境のURL（例：`https://kizuna-entry.vercel.app`） |
| `DB_CONNECTION` | `pgsql` |
| `SESSION_SECURE_COOKIE` | `true` |
| `KIZUNA_ADMIN_ACCOUNTS` | 運営者にするアカウントのメールアドレス（カンマ区切り） |
| `KIZUNA_ADMIN_EMAIL` | 運営の受信用アドレス（例：`kizuna.taikai@gmail.com`） |
| `MAIL_MAILER` | 最初は `log`（メールは送らずログに出す）。実際に届けて試すなら `smtp` にして、下の `MAIL_*` も入れる |
| `MAIL_SCHEME` / `MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD` / `MAIL_FROM_ADDRESS` | Xserver のメールアカウント（`smtps` / `sv○○○○.xserver.jp` / `465` / …） |
| `STRIPE_SECRET` / `STRIPE_WEBHOOK_SECRET` | Stripe の **テストモード** のキー（4 を参照） |
| `KOMOJU_SECRET` / `KOMOJU_WEBHOOK_SECRET` / `KOMOJU_FEE` | KOMOJU の **テストモード** のキー（4 を参照） |

## 4. テスト環境のURLと、反映するブランチ

1. `Settings → Environments → Production → Branch Tracking` を作業ブランチ
   （例：`claude/confident-noether-ho0kwd`）にする。
   これで作業ブランチに push するたびに、固定のURL（`https://<プロジェクト名>.vercel.app`）が新しくなります。
   `main` は Vercel には出しません（`vercel.json` で止めています。main は Xserver 用）。
2. `Deployments` から最新のデプロイを **Redeploy** する。
3. 開いたサイトで `/team/register` から `KIZUNA_ADMIN_ACCOUNTS` のメールアドレスで団体登録し、
   もう一度 Redeploy すると、そのアカウントが運営者（管理画面 `/admin`）になります。

Stripe・KOMOJU の Webhook URL は、このテスト環境のURLで登録します（`docs/deploy-xserver.md` の 6・7 と同じ手順）。

- Stripe：`https://<プロジェクト名>.vercel.app/stripe/webhook`
- KOMOJU：`https://<プロジェクト名>.vercel.app/komoju/webhook`

## 5. テスト環境でできないこと

Vercel はファイルを保存しておけない仕組みのため、次の2つは **一時的にしか保存されません**。
画面の流れは確かめられますが、少し時間がたつと消えたり、「もう一度アップロードしてください」と出ることがあります。
この2つは本番（Xserver）で確かめてください。

- 管理画面の「大会資料（PDF）」のアップロード（URL を入れる方式なら問題なく試せます）
- エクセル添付のエントリー（アップロード → 確認 → 申込み）

## 6. 本番に出す

テスト環境で確認できたら、Claude に「mainを反映して」と伝えます。
作業ブランチを `main` にマージし、GitHub Actions の `deploy` が Xserver にアップロードします
（テスト → アップロード → データベース更新）。進み具合は GitHub の `Actions` タブで見られます。
Xserver 側の初回設定は `docs/deploy-xserver.md` を参照してください。
