<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            if (!Schema::hasColumn('vehicles', 've_team_id')) {
                $table->unsignedBigInteger('ve_team_id')->nullable()->comment('所属车队');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'cu_team_id')) {
                $table->unsignedBigInteger('cu_team_id')->nullable()->comment('所属车队');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            if (Schema::hasColumn('vehicles', 've_team_id')) {
                $table->dropColumn('ve_team_id');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'cu_team_id')) {
                $table->dropColumn('cu_team_id');
            }
        });
    }
};
