<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('admins', 'team_ids')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            $table->jsonb('team_ids')->default(DB::raw("'[]'::jsonb"))->comment('Related team ids');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('admins', 'team_ids')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('team_ids');
        });
    }
};
