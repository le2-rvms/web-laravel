<?php

namespace App\Models\_;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    // 使用自定义 token 表名。
    protected $table = '_personal_access_tokens';
}
