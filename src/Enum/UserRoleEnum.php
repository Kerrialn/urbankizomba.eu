<?php

namespace App\Enum;

enum UserRoleEnum: string
{
    case ROLE_USER = 'role.user';
    case ROLE_ADMIN = 'role.admin';
}
