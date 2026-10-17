<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('team'); // team=団体 / individual=個人 / sheet=エクセル添付のみ（ログインなし）
            $table->string('name'); // 団体：代表者氏名 / 個人：本人氏名
            $table->string('team_name')->nullable(); // 団体名（個人の場合は所属・任意）
            $table->string('login_name')->nullable()->unique(); // 団体名ログイン用（表記ゆれをそろえた団体名）
            $table->string('team_kana')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->unique();
            $table->boolean('is_admin')->default(false);
            $table->unsignedSmallInteger('grades_updated_year')->nullable(); // 年度更新（学年の繰り上げ）をした年度
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
