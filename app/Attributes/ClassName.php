<?php

namespace App\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class ClassName
{
    public function __construct(public string $name, public string $suffix = '') {}
}
