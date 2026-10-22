<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            // アスリートキャリアセンターの会員（個人申し込みのみ）。gold / silver。会員かどうかは事務局が確認する
            $table->string('membership', 10)->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->dropColumn('membership');
        });
    }
};
