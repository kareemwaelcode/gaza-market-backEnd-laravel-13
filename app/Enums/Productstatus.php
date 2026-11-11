<?php

namespace App\Enums;

enum ProductStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Hidden = 'hidden';
}