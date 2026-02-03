<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');

            // Category (MOBILE, ACCESSORY, SERVICE)
            $table->string('category')->default('MOBILE');

            // Brand
            $table->string('brand')->nullable();

            // Network Type (4G, 5G)
            $table->string('network_type')->nullable();

            // Model/Product Name
            $table->string('name');

            // Variant (2/32, 4/64, etc.)
            $table->string('variant')->nullable();

            // Service Type (for SERVICE category)
            $table->string('service_type')->nullable();

            // Purchase Price (Cost)
            $table->decimal('purchase_price', 10, 2)->default(0);

            // Selling Price (MRP)
            $table->decimal('selling_price', 10, 2)->default(0);

            // Stock quantity
            $table->integer('stock_quantity')->default(0);

            // Minimum stock alert
            $table->integer('min_stock_alert')->default(5);

            // SKU / Barcode
            $table->string('sku')->nullable()->unique();

            // Product Image URL
            $table->string('image_url')->nullable();

            // Notes/Description
            $table->text('description')->nullable();

            // Active status
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Indexes for faster queries
            $table->index(['category', 'brand']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
