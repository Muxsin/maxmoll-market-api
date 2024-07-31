<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Canceled = 'canceled';
}
