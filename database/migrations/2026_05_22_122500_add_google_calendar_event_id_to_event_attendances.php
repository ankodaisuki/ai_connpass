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
        Schema::table('event_attendances', function (Blueprint $table) {
            $table->string('google_calendar_event_id')->nullable()->after('attended_at');
        });
    }

    public function down(): void
    {
        Schema::table('event_attendances', function (Blueprint $table) {
            $table->dropColumn('google_calendar_event_id');
        });
    }
};
