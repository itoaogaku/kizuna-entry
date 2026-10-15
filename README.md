# 絆記録会 エントリーシステム

陸上競技の記録会「絆記録会」のエントリー受付システムです。

- 最初の画面で「団体申し込み」か「個人申し込み」を選ぶ
- 団体：代表者が団体登録（初期登録）をし、所属選手を一度だけ登録する。
  これまで提出していたエントリーシート（.xlsx）をそのままアップロードして、まとめて登録することもできる。
  次回からは **団体名＋パスワード** で団体の申込データを開く（団体名は全角・半角、大文字・小文字、空白の違いを区別せず、重複登録はできない）
- 個人：選手本人の情報ごと登録し、メールアドレス＋パスワードでログインする
- 大会ごとに、選手一覧から出場する選手にチェックを入れ、申込み種目・申請タイム・自己ベスト・PRしたい一言を入力する
  （項目はエントリーシートの「申込み」欄と同じ）
- 参加費を自動計算し、Stripe の決済ページでカード払い（コンビニ払いも設定で追加可）
- Stripe からの入金通知（Webhook）でエントリーを「入金済み」にし、入金完了メールを自動送信
- 運営は管理画面で大会・種目（料金・定員・男女）を設定し、エントリー一覧をエントリーシートと同じ列の Excel で出力
  （全種目の一覧＋種目ごとのシート。申請タイムの速い順）

## 技術構成

| 役割 | 使うもの |
|---|---|
| アプリ | PHP 8.3 / Laravel 13（画面は Blade。Node.js のビルドは不要） |
| データベース | MySQL（Xserver）／開発時は SQLite |
| 決済 | Stripe Checkout ＋ Webhook |
| メール | Xserver の SMTP |
| 公開 | GitHub Actions で Xserver に自動アップロード（[手順](docs/deploy-xserver.md)） |

## 画面の流れ

1. `/` で団体／個人を選ぶ
   - 団体：`/team/login`（団体名＋パスワード）、はじめては `/team/register` で団体登録 → `/athletes` 選手登録
   - 個人：`/individual/login`（メール＋パスワード）、はじめては `/individual/register`
2. トップページで大会を選ぶ → `/meets/{id}/entry` 選手にチェック・種目を選ぶ → 確認画面
3. 「支払いへ進む」→ Stripe の決済ページ → 支払い完了で `/entries/{id}/complete`
4. Webhook（`/stripe/webhook`）で入金済みにし、代表者へメール（運営に BCC 可）
5. 決済ページで「戻る」を押すとエントリーは取り消され、選んだ内容は入力欄に戻る

定員は「入金済み＋決済待ち」で数えます。決済ページは60分で期限切れになり、Stripe の
`checkout.session.expired` 通知で決済待ちのエントリーは自動で取り消されて枠が空きます。

## 開発環境での起動

```sh
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed   # 動作確認用のデータを入れる
php artisan serve
```

- 運営：団体申し込みで 団体名 `絆記録会 運営` / `password`
- 団体：団体申し込みで 団体名 `絆AC` / `password`
- 個人：個人申し込みで `solo@example.com` / `password`

Stripe のテスト用キーを `.env` に入れると、決済まで試せます。Webhook をローカルで受けるには
[Stripe CLI](https://docs.stripe.com/stripe-cli) の `stripe listen --forward-to localhost:8000/stripe/webhook` を使います
（キーがなくても、Webhook 以外の画面とテストは動きます）。

## テスト

```sh
php artisan test
vendor/bin/pint --test   # コード書式
```

## 運営アカウントの作成

```sh
php artisan kizuna:make-admin 運営のメールアドレス
```
