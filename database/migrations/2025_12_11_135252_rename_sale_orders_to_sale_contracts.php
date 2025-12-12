<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('sale_contracts') && !Schema::hasTable('sale_contracts')) {
            Schema::rename('sale_contracts', 'sale_contracts');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sale_contracts') && !Schema::hasTable('sale_contracts')) {
            Schema::rename('sale_contracts', 'sale_contracts');
        }
    }
};
