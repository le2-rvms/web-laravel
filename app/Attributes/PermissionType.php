<?php

// app/Attributes/PermissionType.php

namespace App\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class PermissionType
{
    public function __construct(public ?string $zh_CN = null) {}
}
