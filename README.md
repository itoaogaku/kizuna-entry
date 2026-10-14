# 絆記録会 エントリーシステム

陸上競技の記録会「絆記録会」のエントリー受付システムです。

- チームの代表者がアカウントを作り、所属選手を一度だけ登録する（CSVでまとめて登録も可）
- 大会ごとに、選手一覧から出場する選手にチェックを入れ、種目と申告記録を選ぶ
- 参加費を自動計算し、Stripe の決済ページでカード払い（コンビニ払いも設定で追加可）
- Stripe からの入金通知（Webhook）でエントリーを「入金済み」にし、入金完了メールを自動送信
- 運営は管理画面で大会・種目（参加費・定員・男女）を設定し、エントリー一覧を Excel 用 CSV で出力

## 技術構成

| 役割 | 使うもの |
|---|---|
| アプリ | PHP 8.3 / Laravel 13（画面は Blade。Node.js のビルドは不要） |
| データベース | MySQL（Xserver）／開発時は SQLite |
| 決済 | Stripe Checkout ＋ Webhook |
| メール | Xserver の SMTP |
| 公開 | GitHub Actions で Xserver に自動アップロード（[手順](docs/deploy-xserver.md)） |

## 画面の流れ

1. `/register` チーム登録 → `/athletes` 選手登録
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

- 運営：`admin@example.com` / `password`
- チーム：`team@example.com` / `password`

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
