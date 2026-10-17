# Xserver（レンタルサーバー）への公開手順

GitHub の `main` ブランチに変更が入ると、GitHub Actions が自動で Xserver にアップロードします。
最初の1回だけ、次の設定をブラウザ上で行います。

以下の例では、独自ドメインを `example.com`、エントリー用のサブドメインを `entry.example.com` とします。

## 1. Xserver サーバーパネル

1. **PHP Ver.切替**：`example.com` を PHP 8.3 以上にする。
2. **サブドメイン設定**：`entry.example.com` を追加する（無料SSLも有効にする）。
3. **MySQL設定**：
   - データベースを追加（例：`xxxx_kizuna`）
   - ユーザーを追加（例：`xxxx_kizuna`、パスワードを控える）
   - 「MySQL一覧」でユーザーにデータベースへのアクセス権を付ける
4. **メールアカウント設定**：送信元にするアドレスを作る（例：`entry@example.com`、パスワードを控える）。
5. **SSH設定**：
   - 「ONにする」
   - 「公開鍵認証用鍵ペアの生成」で鍵を作り、秘密鍵（`.key` ファイル）をダウンロードする
   - サーバーパネルの「サーバー情報」で **ホスト名**（例：`sv12345.xserver.jp`）と **サーバーID** を控える

## 2. GitHub リポジトリの設定

`Settings → Secrets and variables → Actions` で次を登録します。

**Secrets**

| 名前 | 値 |
|---|---|
| `XSERVER_HOST` | ホスト名（例：`sv12345.xserver.jp`） |
| `XSERVER_USER` | サーバーID |
| `XSERVER_PORT` | `10022` |
| `XSERVER_SSH_KEY` | ダウンロードした秘密鍵ファイルの中身をすべて貼り付け |

**Variables**

| 名前 | 値 |
|---|---|
| `XSERVER_DEPLOY` | `true`（これを入れるまで自動アップロードは動きません） |
| `XSERVER_APP_DIR` | 省略可。アプリを置くフォルダ（初期値 `kizuna-entry`＝ホームディレクトリ直下） |
| `XSERVER_PHP` | 省略可。PHP のパス（初期値 `/usr/bin/php8.3`） |

## 3. 初回のアップロードと .env の作成

1. GitHub の `Actions → deploy → Run workflow` を実行する。
   初回は `.env がありません` で止まりますが、ファイルはアップロードされています。
2. サーバーパネルの「ファイルマネージャ」または SSH で、`~/kizuna-entry/.env` を作る。
   中身は `.env.example` をもとに、次の項目を本番用に書き換えます。

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://entry.example.com
APP_KEY=        ← 下の手順で生成

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=xxxx_kizuna
DB_USERNAME=xxxx_kizuna
DB_PASSWORD=（MySQLのパスワード）

MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=sv12345.xserver.jp
MAIL_PORT=465
MAIL_USERNAME=entry@example.com
MAIL_PASSWORD=（メールアカウントのパスワード）
MAIL_FROM_ADDRESS="entry@example.com"

STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
KOMOJU_SECRET=（KOMOJUの秘密鍵）
KOMOJU_WEBHOOK_SECRET=（KOMOJUのWebhookに設定した秘密トークン）
KIZUNA_ADMIN_EMAIL=（運営の受信用アドレス）
```

3. SSH で `cd ~/kizuna-entry && /usr/bin/php8.3 artisan key:generate` を実行して `APP_KEY` を入れる。
4. もう一度 `Run workflow` を実行する（データベースの表が作られます）。

## 4. サブドメインの公開フォルダをアプリにつなぐ

Laravel は `public` フォルダだけを公開します。SSH で次を実行し、サブドメインの公開フォルダを `~/kizuna-entry/public` へのリンクに置き換えます。

```sh
cd ~/example.com/public_html
mv entry entry.bak          # サブドメイン作成時にできたフォルダを退避
ln -s ~/kizuna-entry/public entry
```

`https://entry.example.com` を開いてトップページが出れば成功です（フォルダの場所はサブドメイン設定画面の「ドキュメントルート」で確認できます）。

## 5. 運営アカウントを作る

1. `https://entry.example.com/team/register` で運営用の団体（例：団体名「絆記録会 運営」）を登録する。
2. SSH で次を実行して、管理画面を使えるようにする。

```sh
cd ~/kizuna-entry && /usr/bin/php8.3 artisan kizuna:make-admin 登録したメールアドレス
```

## 6. Stripe の設定（カード払い）

1. Stripe ダッシュボードを **テストモード** にする。
2. `開発者 → APIキー` のシークレットキー（`sk_test_…`）を `.env` の `STRIPE_SECRET` に入れる。
3. `開発者 → Webhook → エンドポイントを追加`
   - URL：`https://entry.example.com/stripe/webhook`
   - イベント：`checkout.session.completed`、`checkout.session.async_payment_succeeded`、`checkout.session.async_payment_failed`、`checkout.session.expired`
   - 作成後に表示される署名シークレット（`whsec_…`）を `.env` の `STRIPE_WEBHOOK_SECRET` に入れる
4. `設定 → 決済手段` で
   - **カード** と **Link** をオンにする（Link をオンにすると、2回目以降はメールアドレスの確認だけでカード情報を入れずに支払える）
   - **コンビニ決済はオフ** にする（コンビニ払いは KOMOJU で受けるため）
5. `設定 → カスタマーへのメール` で「支払い成功」をオンにすると、Stripe からも領収書メールが届きます。

テスト用カード番号 `4242 4242 4242 4242`（有効期限は未来の日付、CVCは任意）で決済を試せます。

## 7. KOMOJU の設定（コンビニ払い）

1. KOMOJU 管理画面を **テストモード** にする。
2. `設定 → API鍵` の秘密鍵を `.env` の `KOMOJU_SECRET` に入れる。
3. `設定 → Webhook → 追加`
   - URL：`https://entry.example.com/komoju/webhook`
   - 秘密トークン：任意の長い文字列を決めて入力し、同じものを `.env` の `KOMOJU_WEBHOOK_SECRET` に入れる
   - イベント：`payment.authorized`、`payment.captured`、`payment.expired`、`payment.cancelled`、`payment.failed`
4. `設定 → 決済方法` でコンビニ決済を有効にし、**支払期限** を確認する。
   期限までに支払いがないと、エントリーは自動で取り消されて枠が空きます。
   締切後の入金も受け付けますが、画面と「コンビニでのお支払いをお願いします（本日中）」メールで、申込日の当日中に支払うよう案内しています。

`KOMOJU_SECRET` が空のあいだは、確認画面に「コンビニ払い」は表示されません（カードだけで受け付けます）。

## 8. 設定を変えたあと

`.env` を変えたら、SSH で `cd ~/kizuna-entry && /usr/bin/php8.3 artisan optimize` を実行して設定を読み込み直します。
本番に切り替えるときは、Stripe・KOMOJU とも本番モードのキーと Webhook で同じ設定をやり直します。
