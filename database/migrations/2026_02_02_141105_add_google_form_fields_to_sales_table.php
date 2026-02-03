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
        Schema::table('sales', function (Blueprint $table) {
            // Category (MOBILE, ACCESSORY, SERVICE)
            $table->string('category')->default('MOBILE')->after('sale_date');

            // Brand (VIVO, OPPO, POCO, etc.)
            $table->string('brand')->nullable()->after('category');

            // Type (4G, 5G)
            $table->string('network_type')->nullable()->after('brand');

            // Model/Accessory Name (was product_name, keeping for backward compatibility)

            // Variant (2/32, 4/64, etc.)
            $table->string('variant')->nullable()->after('product_name');

            // Service Product (DISPLAY CHANGE, CC BOARD, etc.)
            $table->string('service_product')->nullable()->after('variant');

            // Price (was total_amount, keeping for backward compatibility)

            // Gift (BOAT NECKBAND, etc.)
            $table->string('gift')->nullable()->after('remarks');

            // Add user_id for multi-user support
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn([
                'category',
                'brand',
                'network_type',
                'variant',
                'service_product',
                'gift',
                'user_id'
            ]);
        });
    }
};
