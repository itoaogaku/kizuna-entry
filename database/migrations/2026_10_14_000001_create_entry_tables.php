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
            $table->string('last_name');
            $table->string('first_name');
            $table->string('last_kana');
            $table->string('first_kana');
            $table->string('gender', 1); // M / F
            $table->date('birthdate');
            $table->string('category')->nullable(); // 区分（中学・高校・大学・一般など）
            $table->string('jaaf_id')->nullable(); // 陸連登録番号
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
            $table->boolean('is_published')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 種目
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meet_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // 例：一般男子5000m
            $table->string('gender', 1)->nullable(); // M / F / null=男女共通
            $table->unsignedInteger('fee'); // 1人あたりの参加費（円）
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
            $table->unsignedInteger('total_amount');
            $table->string('stripe_session_id')->nullable()->unique();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        // エントリー明細（選手×種目）
        Schema::create('entry_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('athlete_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('declared_record')->nullable(); // 申告記録（例：15:32.10）
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
