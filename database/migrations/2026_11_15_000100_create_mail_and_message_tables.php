<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aşama 7: e-posta gönderim günlüğü (mail_logs) ve müşteri mesajları (message_threads / messages). Müşteri iletişim
 * formundan yazar, yönetici panelden yanıtlar; yanıt müşteriye e-postayla gider, müşteri gizli bağlantıdan devam eder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40)->index();
            $table->string('to', 190);
            $table->string('subject', 255);
            $table->string('status', 10)->default('sent'); // sent | failed
            $table->string('error', 500)->nullable();
            $table->nullableMorphs('subject_model');
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('message_threads', function (Blueprint $table) {
            $table->id();
            $table->string('token', 40)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('email', 190)->index();
            $table->string('phone', 20)->nullable();
            $table->string('subject', 160);
            $table->string('status', 12)->default('open')->index(); // open | answered | closed
            $table->boolean('unread_admin')->default(true)->index();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('message_threads')->cascadeOnDelete();
            $table->string('sender', 10); // customer | admin
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('body');
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('message_threads');
        Schema::dropIfExists('mail_logs');
    }
};
