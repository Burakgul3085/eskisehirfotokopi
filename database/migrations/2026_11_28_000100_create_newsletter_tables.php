<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-bülten: alt bilgideki formdan kaydolan aboneler (açık rıza zamanı ve IP'siyle) ve yönetimden gönderilen toplu
 * e-postalar. Her e-postada kişiye özel "abonelikten çık" bağlantısı (token) bulunur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190)->unique();
            $table->string('token', 64)->unique();
            $table->string('source', 30)->default('footer');
            $table->string('ip', 45)->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('newsletter_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('subject', 190);
            $table->string('heading', 190);
            $table->text('body');
            $table->string('button_text', 60)->nullable();
            $table->string('button_url', 500)->nullable();
            $table->string('status', 20)->default('sending'); // sending | sent
            $table->unsignedInteger('recipients_count')->default(0);
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_campaigns');
        Schema::dropIfExists('newsletter_subscribers');
    }
};
