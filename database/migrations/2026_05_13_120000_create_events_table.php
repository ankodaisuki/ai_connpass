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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->comment('FK、イベント作成者');
            $table->string('title');
            $table->longText('description')->nullable();
            $table->unsignedTinyInteger('category')
                ->default(6)
                ->comment('1:フロントエンド、2:バックエンド、3:データベース、4:モバイル、5:AI、6:その他');
            $table->string('prefecture', 10)->comment('検索用');
            $table->string('location');
            $table->dateTime('event_date');
            $table->unsignedInteger('capacity');
            $table->unsignedTinyInteger('status')
                ->default(0)
                ->comment('0:下書き、1:公開、2:非公開');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
