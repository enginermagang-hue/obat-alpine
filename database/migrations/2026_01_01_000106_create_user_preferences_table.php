<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('avatar_type', 50)->default('initials');
            $table->string('avatar_path')->nullable();
            $table->enum('posisi_navbar', ['sidebar', 'topbar'])->default('sidebar');
            $table->boolean('sidebar_collapsed')->default(1);
            $table->enum('bahasa', ['id', 'en'])->default('id');
            $table->integer('items_per_halaman')->default(10);
            $table->boolean('notifikasi_email')->default(1);
            $table->boolean('notifikasi_browser')->default(1);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('avatar_dicebear_style', 50)->nullable()->default('avataaars');
            $table->index(['avatar_type', 'bahasa'], 'user_preferences_avatar_type_bahasa_index');
            $table->unique(['user_id'], 'user_preferences_user_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
