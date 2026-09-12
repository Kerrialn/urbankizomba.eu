<?php

namespace App\Enum;

enum UserTokenPurposeEnum: string
{
    case EMAIL_VERIFICATION = 'email_verification';
    case PASSWORD_SETUP = 'password_setup';
    case PASSWORD_RESET = 'password_reset';
}
