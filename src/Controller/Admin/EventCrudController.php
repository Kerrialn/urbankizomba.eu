<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Event;
use App\Enum\EventStatusEnum;
use App\Enum\EventTypeEnum;
use App\Service\Event\PosterStorage;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

/**
 * The review queue. Pending first, and an Approve / Reject button on every
 * row, because that is the one decision this screen exists for.
 *
 * @extends AbstractCrudController<Event>
 */
final class EventCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Event::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Event')
            ->setEntityLabelInPlural('Events')
            ->setDefaultSort([
                'status' => 'DESC',
                'createdAt' => 'DESC',
            ])
            ->setSearchFields(['title', 'organiser', 'city.name', 'venue'])
            ->setPaginatorPageSize(50)
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        $approve = Action::new('approve', 'Approve', 'fa fa-check')
            ->linkToCrudAction('approve')
            ->displayIf(static fn (Event $event): bool => ! $event->isApproved())
            ->asSuccessAction();

        $reject = Action::new('reject', 'Reject', 'fa fa-times')
            ->linkToCrudAction('reject')
            ->displayIf(static fn (Event $event): bool => $event->getStatus() !== EventStatusEnum::REJECTED)
            ->asDangerAction();

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $approve)
            ->add(Crud::PAGE_INDEX, $reject)
            ->add(Crud::PAGE_DETAIL, $approve)
            ->add(Crud::PAGE_DETAIL, $reject)
            ->reorder(Crud::PAGE_INDEX, ['approve', 'reject', Action::DETAIL, Action::EDIT, Action::DELETE]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status')->setChoices($this->enumChoices(EventStatusEnum::cases())))
            ->add(ChoiceFilter::new('type')->setChoices($this->enumChoices(EventTypeEnum::cases())))
            ->add(EntityFilter::new('city'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnDetail();
        yield TextField::new('title');
        yield ChoiceField::new('status')
            ->setChoices($this->enumChoices(EventStatusEnum::cases()))
            ->renderAsBadges([
                EventStatusEnum::PENDING->value => 'warning',
                EventStatusEnum::APPROVED->value => 'success',
                EventStatusEnum::REJECTED->value => 'danger',
            ]);
        yield ChoiceField::new('type')->setChoices($this->enumChoices(EventTypeEnum::cases()));
        yield AssociationField::new('city');
        yield DateField::new('startsAt')->setLabel('Starts');
        yield DateField::new('endsAt')->setLabel('Ends')->hideOnIndex();
        yield TextField::new('schedule')->hideOnIndex();
        yield DateField::new('lastConfirmedAt')->hideOnIndex();
        yield TextField::new('venue')->hideOnIndex();
        yield TextField::new('address')->hideOnIndex();
        yield TextareaField::new('description')->hideOnIndex();
        yield TextField::new('organiser')->hideOnIndex();
        yield TextareaField::new('lineup')->hideOnIndex();
        yield UrlField::new('url')->hideOnIndex();
        yield UrlField::new('ticketUrl')->hideOnIndex();
        yield ImageField::new('poster')
            ->setBasePath(PosterStorage::PUBLIC_PATH)
            ->setUploadDir('public/' . PosterStorage::PUBLIC_PATH)
            ->setUploadedFileNamePattern('[randomhash].[extension]')
            ->hideOnIndex();
        yield TextField::new('slug')->hideOnIndex();
        yield AssociationField::new('submittedBy')->setLabel('Submitted by')->hideOnForm();
        yield TextareaField::new('reviewNote')->setLabel('Review note')->hideOnIndex();
        yield DateTimeField::new('createdAt')->setLabel('Submitted')->hideOnForm();
        yield DateTimeField::new('reviewedAt')->onlyOnDetail();
    }

    /**
     * @param AdminContext<Event> $context
     */
    #[AdminRoute('/{entityId}/approve', name: 'approve')]
    public function approve(AdminContext $context): Response
    {
        $event = $context->getEntity()->getInstance();

        if ($event instanceof Event) {
            $event->approve($event->getReviewNote());
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('"%s" is now on the calendar.', $event->getTitle()));
        }

        return $this->redirectToIndex();
    }

    /**
     * @param AdminContext<Event> $context
     */
    #[AdminRoute('/{entityId}/reject', name: 'reject')]
    public function reject(AdminContext $context): Response
    {
        $event = $context->getEntity()->getInstance();

        if ($event instanceof Event) {
            $event->reject($event->getReviewNote());
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('"%s" has been rejected.', $event->getTitle()));
        }

        return $this->redirectToIndex();
    }

    private function redirectToIndex(): Response
    {
        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::INDEX)
                ->generateUrl(),
        );
    }

    /**
     * @param list<\BackedEnum> $cases
     *
     * @return array<string, \BackedEnum>
     */
    private function enumChoices(array $cases): array
    {
        $choices = [];

        foreach ($cases as $case) {
            $choices[ucfirst(strtolower((string) $case->value))] = $case;
        }

        return $choices;
    }
}
