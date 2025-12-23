<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('admins', 'a_team_ids')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            $table->jsonb('a_team_ids')->default(DB::raw("'[]'::jsonb"))->comment('Related team ids');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('admins', 'a_team_ids')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('a_team_ids');
        });
    }
};
