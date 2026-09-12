<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Validator\TurnstileValidator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class TurnstileType extends AbstractType
{
    public function __construct(
        #[Autowire('%env(CLOUDFLARE_TURNSTILE_SITE_KEY)%')]
        private readonly string $siteKey,
    ) {
    }

    public function getParent(): ?string
    {
        return HiddenType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'mapped' => false,
            'error_bubbling' => true,
            'constraints' => [
                new TurnstileValidator(),
            ],
        ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['site_key'] = $this->siteKey;
    }

    public function getBlockPrefix(): string
    {
        return 'turnstile';
    }
}
