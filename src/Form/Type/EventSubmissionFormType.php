<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\DataTransferObject\EventSubmissionDto;
use App\Entity\City;
use App\Entity\Event;
use App\Enum\EventTypeEnum;
use App\Repository\CityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

final class EventSubmissionFormType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly CityRepository $cityRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => $this->translator->trans('submit.field.title'),
                'attr' => [
                    'maxlength' => 140,
                ],
            ])
            // Pills rather than a select: five choices all visible at once is
            // the decision, and the choice changes which fields follow.
            ->add('type', SelectionType::class, [
                'label' => $this->translator->trans('submit.field.type'),
                'choices' => EventTypeEnum::cases(),
                'choice_label' => static fn (EventTypeEnum $type): string => $type->label(),
                'choice_value' => static fn (?EventTypeEnum $type): ?string => $type?->value,
                'choice_attr' => static fn (EventTypeEnum $type): array => [
                    'data-event-type-recurring' => $type->isRecurring() ? '1' : '0',
                    'data-event-type-target' => 'type',
                    'data-action' => 'change->event-type#update',
                ],
                'expanded' => true,
                'multiple' => false,
                'placeholder' => false,
            ])
            ->add('city', EntityType::class, [
                'label' => $this->translator->trans('submit.field.city'),
                'class' => City::class,
                'choices' => $this->cityRepository->findAllOrdered(),
                'choice_label' => static fn (City $city): string => $city->getName(),
                'group_by' => static fn (City $city): string => $city->getCountryName(),
                'placeholder' => $this->translator->trans('submit.field.city_placeholder'),
                'required' => false,
            ])
            ->add('newCityName', TextType::class, [
                'label' => $this->translator->trans('submit.field.new_city_name'),
                'help' => $this->translator->trans('submit.field.new_city_help'),
                'required' => false,
            ])
            ->add('newCityCountry', CountryType::class, [
                'label' => $this->translator->trans('submit.field.new_city_country'),
                'placeholder' => $this->translator->trans('submit.field.country_placeholder'),
                'required' => false,
                // Europe, broadly: the site's scope. A country missing here is
                // one email away from being added.
                'choice_filter' => static fn (?string $code): bool => $code !== null && in_array($code, self::EUROPEAN_COUNTRIES, true),
            ])
            ->add('startsAt', DateType::class, [
                'label' => $this->translator->trans('submit.field.starts_at'),
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('endsAt', DateType::class, [
                'label' => $this->translator->trans('submit.field.ends_at'),
                'help' => $this->translator->trans('submit.field.ends_at_help'),
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('schedule', TextType::class, [
                'label' => $this->translator->trans('submit.field.schedule'),
                'help' => $this->translator->trans('submit.field.schedule_help'),
                'required' => false,
                'attr' => [
                    'maxlength' => 160,
                    'placeholder' => $this->translator->trans('submit.field.schedule_placeholder'),
                ],
            ])
            ->add('venue', TextType::class, [
                'label' => $this->translator->trans('submit.field.venue'),
                'required' => false,
            ])
            ->add('address', TextType::class, [
                'label' => $this->translator->trans('submit.field.address'),
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'label' => $this->translator->trans('submit.field.description'),
                'help' => $this->translator->trans('submit.field.description_help'),
                'attr' => [
                    'rows' => 8,
                    'maxlength' => Event::MAX_DESCRIPTION_LENGTH,
                ],
            ])
            ->add('organiser', TextType::class, [
                'label' => $this->translator->trans('submit.field.organiser'),
                'required' => false,
            ])
            ->add('lineup', TextareaType::class, [
                'label' => $this->translator->trans('submit.field.lineup'),
                'help' => $this->translator->trans('submit.field.lineup_help'),
                'required' => false,
                'attr' => [
                    'rows' => 3,
                ],
            ])
            ->add('url', UrlType::class, [
                'label' => $this->translator->trans('submit.field.url'),
                'required' => false,
                'default_protocol' => 'https',
            ])
            ->add('ticketUrl', UrlType::class, [
                'label' => $this->translator->trans('submit.field.ticket_url'),
                'required' => false,
                'default_protocol' => 'https',
            ])
            ->add('poster', FileType::class, [
                'label' => $this->translator->trans('submit.field.poster'),
                'help' => $this->translator->trans('submit.field.poster_help'),
                'required' => false,
                'attr' => [
                    'accept' => 'image/jpeg,image/png,image/webp',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EventSubmissionDto::class,
        ]);
    }

    /**
     * ISO 3166-1 alpha-2 codes of the countries the calendar covers.
     */
    public const array EUROPEAN_COUNTRIES = [
        'AL', 'AD', 'AT', 'BY', 'BE', 'BA', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR',
        'HU', 'IS', 'IE', 'IT', 'XK', 'LV', 'LI', 'LT', 'LU', 'MT', 'MD', 'MC', 'ME', 'NL', 'MK', 'NO',
        'PL', 'PT', 'RO', 'RU', 'SM', 'RS', 'SK', 'SI', 'ES', 'SE', 'CH', 'TR', 'UA', 'GB', 'VA',
    ];
}
