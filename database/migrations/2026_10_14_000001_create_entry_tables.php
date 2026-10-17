<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 選手（チームごとに初期登録し、毎回の大会で使い回す）
        Schema::create('athletes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('last_name'); // 姓
            $table->string('first_name'); // 名
            $table->string('kana'); // フリガナ（全角カタカナ。例：スズキ タロウ）
            $table->unsignedTinyInteger('grade')->nullable(); // 学年（学生の場合）
            $table->string('gender', 1); // M / F
            $table->string('association')->nullable(); // 所属陸協（例：東京）
            $table->string('category')->nullable(); // 種別（中学生・高校生・大学生・一般）
            $table->date('birthdate')->nullable(); // 生年月日（任意）
            $table->string('jaaf_id')->nullable(); // 陸連登録番号（任意）
            $table->boolean('is_active')->default(true); // 引退・卒業した選手は一覧から外す
            $table->timestamps();
        });

        // 大会（開催回）
        Schema::create('meets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('held_on');
            $table->string('venue')->nullable();
            $table->dateTime('entry_opens_at');
            $table->dateTime('entry_closes_at');
            $table->unsignedInteger('max_events_per_athlete')->default(2);
            $table->time('start_time')->nullable(); // 競技開始予定時刻
            // 区分別の参加料（入っていれば種目の料金より優先）。中学生 / 高校生・大学生 / 一般・実業団
            $table->unsignedInteger('fee_junior_high')->nullable();
            $table->unsignedInteger('fee_student')->nullable();
            $table->unsignedInteger('fee_general')->nullable();
            $table->boolean('is_published')->default(false);
            $table->text('notes')->nullable();
            // 大会開催ページに出す内容
            $table->string('division')->default('公認の部'); // 部門
            $table->string('eligible')->nullable(); // 対象（例：中学生・高校生・大学生・一般）
            $table->string('outline_url')->nullable(); // 大会要項（URL）
            $table->string('outline_path')->nullable(); // 大会要項（アップロードしたPDF）
            $table->string('program_url')->nullable();
            $table->string('program_path')->nullable();
            $table->string('program_note')->nullable(); // 例：0628更新
            $table->string('result_url')->nullable();
            $table->string('result_path')->nullable();
            $table->string('photo_url')->nullable(); // 写真館
            $table->string('press_url')->nullable(); // 取材・撮影のお申込み
            $table->timestamps();
        });

        // 種目
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meet_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // 例：一般男子5000m
            $table->string('gender', 1)->nullable(); // M / F / null=男女共通
            $table->unsignedInteger('fee')->default(0); // 1人あたりの参加費（円）。大会の区分別参加料があればそちらを使う
            $table->unsignedInteger('capacity')->nullable(); // 定員（null=無制限）
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // エントリー（1回の申込＝1回の決済）
        Schema::create('entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meet_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending / paid / cancelled
            $table->string('source', 20)->default('web'); // web=Webエントリー / sheet=エクセル添付
            $table->string('sheet_path')->nullable(); // 添付されたエクセルの保存先
            $table->unsignedInteger('total_amount');
            $table->unsignedInteger('payment_fee')->default(0); // コンビニ払いの手数料（合計金額に含む）
            $table->string('payment_method', 20)->default('card'); // card=カード（Stripe） / konbini=コンビニ（KOMOJU）
            $table->string('payment_session_id')->nullable()->unique(); // 決済ページのID
            $table->string('payment_id')->nullable(); // 決済のID（Stripe PaymentIntent / KOMOJU Payment）
            $table->string('payment_instructions_url')->nullable(); // コンビニ払いの払込票URL
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        // エントリー明細（選手×種目）
        Schema::create('entry_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('athlete_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('declared_record')->nullable(); // 申請タイム（例：14'30）
            $table->string('personal_best')->nullable(); // 自己ベスト（例：14'45）
            $table->string('pr_comment')->nullable(); // PRしたい一言（実況で使用）
            $table->unsignedInteger('fee');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_items');
        Schema::dropIfExists('entries');
        Schema::dropIfExists('events');
        Schema::dropIfExists('meets');
        Schema::dropIfExists('athletes');
    }
};
