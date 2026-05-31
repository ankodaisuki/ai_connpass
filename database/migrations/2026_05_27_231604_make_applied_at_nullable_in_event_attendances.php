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
        Schema::table('event_attendances', function (Blueprint $table): void {
            $table->timestamp('applied_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Waitlisted レコードは applied_at が NULL のため、NOT NULL 化する前に現在時刻で埋める
        DB::table('event_attendances')
            ->whereNull('applied_at')
            ->update(['applied_at' => now()]);

        Schema::table('event_attendances', function (Blueprint $table): void {
            $table->timestamp('applied_at')->useCurrent()->nullable(false)->change();
        });
    }
};
