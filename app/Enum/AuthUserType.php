<?php

namespace App\Enum;

use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use Illuminate\Support\Facades\Auth;

class AuthUserType
{
    public const string ADMIN = 'st';

    public const string CUSTOMER = 'cu';

    public static function getValue(): ?string
    {
        if (!Auth::check()) {
            return null;
        }

        $auth_user = Auth::user();

        $format = '%s:%s:%s';

        return match (true) {
            $auth_user instanceof Admin    => sprintf($format, AuthUserType::ADMIN, $auth_user->id, $auth_user->name),
            $auth_user instanceof Customer => sprintf($format, AuthUserType::CUSTOMER, $auth_user->cu_id, $auth_user->contact_name),
        };
    }
}
