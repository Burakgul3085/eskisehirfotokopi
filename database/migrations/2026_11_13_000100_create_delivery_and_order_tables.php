<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Teslimat bölgeleri, adresler, siparişler ve favoriler (docs/01 §4, §7; K6, K8).
 * Ödeme kapıda (nakit/kart); ödeme altyapısı ve fatura yok.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_districts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->decimal('fee', 10, 2)->default(0);
            $table->decimal('free_threshold', 10, 2)->nullable(); // boşsa genel eşik
            $table->decimal('min_order', 10, 2)->nullable();      // boşsa genel en düşük tutar
            $table->unsignedTinyInteger('extra_days')->default(0); // uzak ilçe: tahmini teslime eklenen gün
            $table->boolean('is_active')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_neighborhoods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->constrained('delivery_districts')->cascadeOnDelete();
            $table->string('name', 120);
            $table->decimal('fee', 10, 2)->nullable(); // boşsa ilçe ücreti
            $table->boolean('is_active')->default(true);
            $table->unique(['district_id', 'name']);
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 40);
            $table->string('name', 120);
            $table->string('phone', 20);
            $table->foreignId('district_id')->nullable()->constrained('delivery_districts')->nullOnDelete();
            $table->string('neighborhood', 120);
            $table->string('address', 500);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->string('tracking_token', 40);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('received')->index();
            $table->string('customer_name', 120);
            $table->string('phone', 20)->index();
            $table->string('email', 190)->nullable();
            $table->foreignId('district_id')->nullable()->constrained('delivery_districts')->nullOnDelete();
            $table->string('district_name', 80);
            $table->string('neighborhood', 120);
            $table->string('address', 500);
            $table->string('delivery_slot', 40)->nullable();
            $table->date('delivery_date')->nullable()->index();
            $table->string('payment_method', 10); // cash | card
            $table->decimal('subtotal', 10, 2);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('note', 1000)->nullable();
            $table->string('admin_note', 1000)->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10)->default('product'); // product | print
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('print_job_id')->nullable()->index();
            $table->string('title', 255);
            $table->string('print_type', 10)->nullable(); // bw | color
            $table->decimal('unit_price', 10, 2);
            $table->unsignedSmallInteger('quantity');
            $table->decimal('line_total', 10, 2);
            $table->json('meta')->nullable();
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->string('note', 500)->nullable();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->boolean('customer_notified')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['user_id', 'product_id']);
        });

        // Eskişehir ilçeleri: merkez ilçeler açık, diğerleri yönetimden açılır (K6: ücretler örnek, panelden değişir).
        $now = now();
        $districts = ['Odunpazarı', 'Tepebaşı', 'Alpu', 'Beylikova', 'Çifteler', 'Günyüzü', 'Han', 'İnönü', 'Mahmudiye', 'Mihalgazi',
            'Mihalıççık', 'Sarıcakaya', 'Seyitgazi', 'Sivrihisar'];
        foreach ($districts as $i => $name) {
            DB::table('delivery_districts')->insert([
                'name' => $name, 'fee' => $i < 2 ? 40 : 100, 'is_active' => $i < 2, 'position' => $i + 1,
                'extra_days' => $i < 2 ? 0 : 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('delivery_neighborhoods');
        Schema::dropIfExists('delivery_districts');
    }
};
