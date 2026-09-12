<?php

declare(strict_types=1);

namespace App\DataTransferObject;

use Symfony\Component\Validator\Constraints as Assert;

final class RegistrationFormDto
{
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public ?string $email = null;
}
