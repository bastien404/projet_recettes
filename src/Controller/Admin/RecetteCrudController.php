<?php

namespace App\Controller\Admin;

use App\Entity\Recette;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class RecetteCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Recette::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('nom'),
            // FIX: hideOnIndex() prevents EasyAdmin from trying to render/truncate HTML in the list
            TextEditorField::new('description')->hideOnIndex(),

            // Configure specifics for numeric fields
            IntegerField::new('temps')->setLabel('Temps (minutes)')->setHelp('Durée de préparation/cuisson'),
            IntegerField::new('nbPersonnes')->setLabel('Nombre de personnes'),

            // Assuming Difficulty is a score, e.g., 1-5
            IntegerField::new('difficulte')->setLabel('Difficulté (1-5)'),

            // MoneyField requires a currency. Assuming EUR for a French context.
            MoneyField::new('prix')->setCurrency('EUR')->setStoredAsCents(false),

            BooleanField::new('isFavorite'),

            // Relation with Ingredients
            AssociationField::new('ingredients')->autocomplete(),

            // Timestamps are usually read-only or automatic
            DateTimeField::new('createdAt')->hideOnForm(),
            DateTimeField::new('updatedAt')->hideOnForm(),
        ];
    }
}