<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // まず NULL 許可で追加
        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('end_date')->nullable()->after('event_date');
        });

        // 既存データを「開始 + 2時間」でバックフィル
        $expression = match (DB::getDriverName()) {
            'mysql', 'mariadb' => 'DATE_ADD(event_date, INTERVAL 2 HOUR)',
            'pgsql' => "event_date + INTERVAL '2 hours'",
            default => "datetime(event_date, '+2 hours')", // sqlite
        };
        DB::statement("UPDATE events SET end_date = {$expression} WHERE end_date IS NULL");

        // NOT NULL 制約を付与
        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('end_date')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('end_date');
        });
    }
};
