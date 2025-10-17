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
        Schema::table('chat_room_user', function (Blueprint $table) {
            $table->enum('role', ['member', 'admin', 'owner'])->default('member');
            $table->boolean('is_muted')->default(false);
            $table->timestamp('muted_until')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_room_user', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_muted', 'muted_until']);
        });
    }
};
