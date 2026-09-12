<?php

declare(strict_types=1);

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Renders expanded choices (radio or checkbox) as Bootstrap btn-check toggles.
 *
 * Usage:
 *   ->add('field', BtnCheckType::class, [
 *       'choices'   => [...],     // or 'class' + 'enum' via EnumType parent
 *       'multiple'  => false,     // false = radio-style (single select)
 *       'btn_class' => 'btn-outline-primary',   // optional, default btn-outline-secondary
 *   ])
 *
 * For EnumType pass 'enum' option as you normally would via EnumType — you still
 * need to use ChoiceType conventions here; if you want EnumType convenience just
 * pass the choices manually or subclass further.
 */
final class SelectionType extends AbstractType
{
    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'selection';
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['btn_class'] = $options['btn_class'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'expanded' => true,
            'btn_class' => 'btn-outline-secondary',
        ]);

        $resolver->setAllowedTypes('btn_class', 'string');
    }
}
