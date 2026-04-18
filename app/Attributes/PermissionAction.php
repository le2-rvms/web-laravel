<?php

// app/Attributes/PermissionAction.php

namespace App\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class PermissionAction
{
    public const string READ  = 'read';
    public const string WRITE = 'write';

    public string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}
