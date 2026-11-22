<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('video_path')->nullable()->after('body_en');
            $table->string('video_poster_path')->nullable()->after('video_path');
            $table->string('video_position', 10)->default('top')->after('video_poster_path');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['video_path', 'video_poster_path', 'video_position']);
        });
    }
};
