<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('location')->nullable()->change();
            $table->string('online_url', 2048)->nullable()->after('location');
            $table->string('online_password')->nullable()->after('online_url');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['online_url', 'online_password']);
            $table->string('location')->nullable(false)->change();
        });
    }
};
