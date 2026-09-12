<?php

declare(strict_types=1);

namespace App\DataTransferObject;

use Symfony\Component\Validator\Constraints as Assert;

final class LoginCodeDto
{
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{6}$/', message: 'login.error.code_format')]
    public ?string $code = null;
}
