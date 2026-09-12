<?php

namespace App\Enum;

enum FlashEnum: string
{
    case SUCCESS = 'success';
    case INFO = 'info';
    case ERROR = 'error';
    case WARNING = 'warning';
}
