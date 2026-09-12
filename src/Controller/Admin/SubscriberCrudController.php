<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Subscriber;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;

/**
 * Read and delete only. An admin cannot confirm an address on someone's
 * behalf: the whole point of double opt-in is that only the inbox can.
 *
 * @extends AbstractCrudController<Subscriber>
 */
final class SubscriberCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Subscriber::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Subscriber')
            ->setEntityLabelInPlural('Subscribers')
            ->setDefaultSort([
                'createdAt' => 'DESC',
            ])
            ->setSearchFields(['email'])
            ->setPaginatorPageSize(100);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT);
    }

    public function configureFields(string $pageName): iterable
    {
        yield EmailField::new('email');
        yield DateTimeField::new('confirmedAt')->setLabel('Confirmed');
        yield DateTimeField::new('unsubscribedAt')->setLabel('Unsubscribed');
        yield DateTimeField::new('lastSentAt')->setLabel('Last digest');
        yield DateTimeField::new('createdAt')->setLabel('Signed up');
    }
}
