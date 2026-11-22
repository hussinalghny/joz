<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // 0 for every product until the owner drags them into an order, so
            // nothing moves on the storefront the day this ships.
            $table->unsignedInteger('sort_order')->default(0)->after('is_featured');
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
