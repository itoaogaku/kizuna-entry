# Xserver（レンタルサーバー）への公開手順

GitHub の `main` ブランチに変更が入ると、GitHub Actions が自動で Xserver にアップロードします。
サーバーの設定ファイル（`.env`）の作成、公開フォルダのリンク、データベースの更新、運営者の権限付けも自動で行うので、
SSH でコマンドを打つ必要はありません。最初の1回だけ、次の設定をブラウザ上で行います。

本番の例：サーバー `sv14853`（サーバーID `accxserver`）、サイト `https://taikai.acckizuna.com`

## 1. Xserver サーバーパネル

1. **ドメイン設定**：`acckizuna.com` を追加（PHP 8.3）。ドメイン本体は Wix のまま。
2. **サブドメイン設定**：`taikai.acckizuna.com` を追加
   （ドキュメントルートは `/acckizuna.com/public_html/taikai.acckizuna.com/`）。
3. **Wix の DNS**：A レコード `taikai` → サーバー情報の IP アドレス。反映後に **SSL設定** で `taikai.acckizuna.com` に独自SSLを追加。
4. **MySQL設定**：ユーザー `accxserver_kzn` とデータベース `accxserver_kzn`（MariaDB10.5・UTF-8）を作り、アクセス権を付ける。
5. **メールアカウント設定**：送信元のアドレスを作る（例：`taikai@acckizuna.com`）。
6. **SSH設定**：ON にし、「公開鍵を登録」で鍵ペアを自動生成（ラベル例 `kizuna-entry`）。
   秘密鍵（`.key`）がダウンロードされる。パスフレーズを控えておく。

## 2. GitHub リポジトリの設定

`Settings → Secrets and variables → Actions` で次を登録します。

**Secrets**（「New repository secret」）

| 名前 | 値 |
|---|---|
| `XSERVER_HOST` | `sv14853.xserver.jp` |
| `XSERVER_USER` | `accxserver` |
| `XSERVER_PORT` | `10022` |
| `XSERVER_SSH_KEY` | ダウンロードした秘密鍵ファイル（`.key`）をメモ帳で開き、中身をすべて貼り付け |
| `XSERVER_SSH_PASSPHRASE` | 鍵ペアを作るときに決めたパスフレーズ |
| `XSERVER_ENV` | サーバーの設定（下の「XSERVER_ENV の中身」をすべて貼り付け） |

**Variables**（「Variables」タブ →「New repository variable」）

| 名前 | 値 |
|---|---|
| `XSERVER_DEPLOY` | `true`（これを入れるまで自動アップロードは動きません） |
| `XSERVER_DOCROOT` | `acckizuna.com/public_html/taikai.acckizuna.com` |
| `XSERVER_APP_DIR` | 省略可。アプリを置くフォルダ（初期値 `kizuna-entry`＝ホームディレクトリ直下） |
| `XSERVER_PHP` | 省略可。PHP のパス（初期値 `/usr/bin/php8.3`） |

### XSERVER_ENV の中身

`（ ）` の部分を書き換えて貼り付けます。`APP_KEY` は空のままで大丈夫です（初回にサーバーで自動作成し、以後は引き継ぎます）。
パスワードに `#` や空白が入るときは `"` で囲みます。

```
APP_NAME="絆記録会 エントリー"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://taikai.acckizuna.com
APP_KEY=
APP_LOCALE=ja
APP_FALLBACK_LOCALE=ja
APP_FAKER_LOCALE=ja_JP
LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=accxserver_kzn
DB_USERNAME=accxserver_kzn
DB_PASSWORD="（MySQLのパスワード）"

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local

MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=sv14853.xserver.jp
MAIL_PORT=465
MAIL_USERNAME=taikai@acckizuna.com
MAIL_PASSWORD="（メールアカウントのパスワード）"
MAIL_FROM_ADDRESS=taikai@acckizuna.com
MAIL_FROM_NAME="絆記録挑戦会"
MAIL_REPLY_TO_ADDRESS=kizuna.taikai@gmail.com

STRIPE_SECRET=（sk_test_…）
STRIPE_WEBHOOK_SECRET=（whsec_…）
KOMOJU_SECRET=（KOMOJUの秘密鍵）
KOMOJU_WEBHOOK_SECRET=（KOMOJUのWebhookに設定した秘密トークン）
KOMOJU_FEE=0

KIZUNA_ADMIN_EMAIL=kizuna.taikai@gmail.com
KIZUNA_ADMIN_ACCOUNTS=（運営者にするアカウントのメールアドレス）
```

Stripe・KOMOJU がまだなら、その行は空のままで構いません（カード・コンビニ払いのボタンが出ないだけです）。

## 3. 初回のアップロード

`main` に反映されると（「mainを反映して」と指示）、`Actions → deploy` が動きます。
緑のチェックが付いたら `https://taikai.acckizuna.com` を開いてトップページが出るか確かめます。

## 4. 運営アカウントを作る

1. `https://taikai.acckizuna.com/team/register` で運営用の団体（例：団体名「絆記録会 運営」）を登録する。
   メールアドレスは `KIZUNA_ADMIN_ACCOUNTS` に書いたものにする。
2. GitHub の `Actions → deploy → Run workflow` をもう一度実行すると、そのアカウントが運営者（管理画面 `/admin`）になります。

## 5. 設定を変えるとき

Secrets の `XSERVER_ENV` を書き換えて（「Update」）、`Actions → deploy → Run workflow` を実行します。
テストモードから本番に切り替えるときも同じです（Stripe・KOMOJU の本番キーと本番用 Webhook に差し替える）。

## 6. Stripe の設定（カード払い）

1. Stripe ダッシュボードを **テストモード** にする。
2. `開発者 → APIキー` のシークレットキー（`sk_test_…`）を Secrets `XSERVER_ENV` の `STRIPE_SECRET` に入れる。
3. `開発者 → Webhook → エンドポイントを追加`
   - URL：`https://taikai.acckizuna.com/stripe/webhook`
   - イベント：`checkout.session.completed`、`checkout.session.async_payment_succeeded`、`checkout.session.async_payment_failed`、`checkout.session.expired`
   - 作成後に表示される署名シークレット（`whsec_…`）を Secrets `XSERVER_ENV` の `STRIPE_WEBHOOK_SECRET` に入れる
4. `設定 → 決済手段` で
   - **カード** と **Link** をオンにする（Link をオンにすると、2回目以降はメールアドレスの確認だけでカード情報を入れずに支払える）
   - **コンビニ決済はオフ** にする（コンビニ払いは KOMOJU で受けるため）
5. `設定 → カスタマーへのメール` で「支払い成功」をオンにすると、Stripe からも領収書メールが届きます。

テスト用カード番号 `4242 4242 4242 4242`（有効期限は未来の日付、CVCは任意）で決済を試せます。

## 7. KOMOJU の設定（コンビニ払い）

1. KOMOJU 管理画面を **テストモード** にする。
2. `設定 → API鍵` の秘密鍵を Secrets `XSERVER_ENV` の `KOMOJU_SECRET` に入れる。
3. `設定 → Webhook → 追加`
   - URL：`https://taikai.acckizuna.com/komoju/webhook`
   - 秘密トークン：任意の長い文字列を決めて入力し、同じものを Secrets `XSERVER_ENV` の `KOMOJU_WEBHOOK_SECRET` に入れる
   - イベント：`payment.authorized`、`payment.captured`、`payment.expired`、`payment.cancelled`、`payment.failed`
4. `設定 → 決済方法` でコンビニ決済を有効にし、**支払期限** を確認する。
   期限までに支払いがないと、エントリーは自動で取り消されて枠が空きます。
   締切後の入金も受け付けますが、画面と「コンビニでのお支払いをお願いします（本日中）」メールで、申込日の当日中に支払うよう案内しています。

`KOMOJU_SECRET` が空のあいだは、確認画面に「コンビニ払い」は表示されません（カードだけで受け付けます）。

## 8. 大会開催ページの写真・ロゴ

- 大会開催ページの写真は `public/images/hero.jpg`、ロゴは `public/images/logo.png` です。
  今は現在のサイトの画面から切り出したもの（解像度が低め）なので、元の写真・ロゴのデータがあれば同じ名前で差し替えてください
  （写真は横長・幅2000px程度、ロゴは背景が透明なPNGが最適です）。
- 大会要項・タイムテーブル・組編成・プログラム・リザルトのPDFは `storage/app/public/meets` に保存され、`/storage/...` で公開されます（自動アップロード時に `storage:link` を実行します）。
- 1ファイル40MBまで貼れるようにしています。写真判定機のリザルトなど大きなPDFを貼るときは、サーバーパネルの「php.ini設定」で
  `upload_max_filesize` と `post_max_size` を 50M 程度にしておいてください。
