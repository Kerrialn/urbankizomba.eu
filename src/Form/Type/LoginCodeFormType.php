<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\DataTransferObject\LoginCodeDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class LoginCodeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('code', PinCodeType::class, [
            'label' => false,
            'length' => 6,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LoginCodeDto::class,
            // The authenticator intercepts this POST, so the form is never
            // handled by the controller: the token is checked by CsrfTokenBadge.
            'csrf_token_id' => 'authenticate',
            'csrf_field_name' => '_token',
        ]);
    }
}
