<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meets', function (Blueprint $table) {
            // スタートリスト・タイムテーブルの状態：none=未公開 / provisional=暫定版を公開 / final=確定（プログラム完成）
            $table->string('start_list_status', 20)->default('none');
            $table->timestamp('start_list_published_at')->nullable();
            $table->text('program_notes')->nullable(); // プログラムに載せる注意事項（招集・ナンバーなど）
        });

        // レース（種目×男女）。タイムテーブルの1行になる
        Schema::create('races', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('gender', 1); // M / F
            $table->string('division', 20)->default('open'); // junior=中学生の部 / open=高校生・大学生・一般の部
            $table->time('start_time')->nullable(); // 1組目のスタート時刻
            $table->unsignedInteger('heat_size')->default(20); // 1組の最大人数
            $table->unsignedInteger('heat_interval')->default(10); // 組と組の間隔（分）
            $table->boolean('fastest_last')->default(true); // 速い組を最後にする
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['meet_id', 'event_id', 'gender', 'division']);
        });

        // スタートリスト（レースの何組目の何番目か）
        Schema::create('start_list_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('race_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entry_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('heat');
            $table->unsignedInteger('position');
            $table->timestamps();
            $table->unique('entry_item_id');
        });

        // 取材・撮影のお申込み
        Schema::create('press_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meet_id')->constrained()->cascadeOnDelete();
            $table->string('media_name'); // 媒体名・社名
            $table->string('contact_name'); // 担当者氏名
            $table->string('email');
            $table->string('phone');
            $table->string('coverage'); // 取材 / 撮影 / 取材・撮影
            $table->unsignedInteger('people')->default(1); // 来場人数
            $table->text('purpose')->nullable(); // 掲載予定・目的
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('press_requests');
        Schema::dropIfExists('start_list_entries');
        Schema::dropIfExists('races');
        Schema::table('meets', function (Blueprint $table) {
            $table->dropColumn(['start_list_status', 'start_list_published_at', 'program_notes']);
        });
    }
};
