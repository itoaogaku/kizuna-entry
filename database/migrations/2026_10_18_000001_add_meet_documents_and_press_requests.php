<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 大会資料（PDF）：タイムテーブル・組編成（スタートリスト）を追加し、各資料に「0628更新」などのメモを付けられるようにする
        Schema::table('meets', function (Blueprint $table) {
            $table->string('outline_note')->nullable();
            $table->string('timetable_url')->nullable();
            $table->string('timetable_path')->nullable();
            $table->string('timetable_note')->nullable();
            $table->string('startlist_url')->nullable();
            $table->string('startlist_path')->nullable();
            $table->string('startlist_note')->nullable();
            $table->string('result_note')->nullable();
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
        Schema::table('meets', function (Blueprint $table) {
            $table->dropColumn(['outline_note', 'timetable_url', 'timetable_path', 'timetable_note',
                'startlist_url', 'startlist_path', 'startlist_note', 'result_note']);
        });
    }
};
