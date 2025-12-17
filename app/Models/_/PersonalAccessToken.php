<?php

namespace App\Models\_;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $table = '_personal_access_tokens'; // 这里改成你想要的表名
}
