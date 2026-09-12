<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\DataTransferObject\NewsletterSignupDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

final class NewsletterSignupFormType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => $this->translator->trans('newsletter.field.email'),
                'attr' => [
                    'autocomplete' => 'email',
                ],
            ])
            // The form sits on every public page with no login in front of
            // it, and a confirmation email goes to whatever address is typed.
            ->add('turnstile', TurnstileType::class, [
                'label' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => NewsletterSignupDto::class,
        ]);
    }
}
