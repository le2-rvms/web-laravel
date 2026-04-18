<?php

namespace App\Attributes;

enum ColumnType: string
{
    //    case TEXT = 'text';
    case DATE     = 'date';
    case DATETIME = 'datetime';
    //    case ENUM = 'enum';
}
