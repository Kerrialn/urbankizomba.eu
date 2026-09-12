<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\City;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\CountryField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Mostly for tidying: renaming "koln" to "Cologne" after a submitter typed
 * it, or correcting a country. Deleting a city with events on it fails on the
 * foreign key, which is the right answer.
 *
 * @extends AbstractCrudController<City>
 */
final class CityCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return City::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('City')
            ->setEntityLabelInPlural('Cities')
            ->setDefaultSort([
                'country' => 'ASC',
                'name' => 'ASC',
            ])
            ->setSearchFields(['name', 'country', 'slug'])
            ->setPaginatorPageSize(100);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name');
        yield CountryField::new('country');
        yield TextField::new('slug')
            ->setHelp('Changing it breaks every link to the city page. Leave it unless the name was wrong.');
        yield DateTimeField::new('createdAt')->hideOnForm();
    }
}
