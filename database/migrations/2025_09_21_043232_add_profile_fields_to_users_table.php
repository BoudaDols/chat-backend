<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_url')->nullable();
            $table->timestamp('last_seen')->nullable();
            $table->text('bio')->nullable();
            $table->string('phone')->nullable();
            $table->json('privacy_settings')->nullable();
        });
        
        // Update existing status column (SQLite compatible)
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE users MODIFY COLUMN status ENUM('online', 'offline', 'away', 'busy') DEFAULT 'offline'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_url', 'last_seen', 'bio', 'phone', 'privacy_settings']);
        });
        
        // Revert status column to string
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE users MODIFY COLUMN status VARCHAR(255)");
        }
    }
};
