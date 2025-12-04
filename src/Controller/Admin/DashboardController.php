<?php

namespace App\Controller\Admin;

use App\Entity\Ingredient;
use App\Entity\Recette;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;


class DashboardController extends AbstractDashboardController
{
    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        // --- Option 3 ---
        // Ensure this line is active and rendering your custom template
        return $this->render('admin/dashboard.html.twig');

        // Make sure the lines below are commented out or removed
        // return parent::index(); // <-- MUST be commented or removed
        // $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);
        // return $this->redirect($adminUrlGenerator->setController(IngredientCrudController::class)->generateUrl()); // <-- Option 1, should be commented
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Mes Recettes 🍲');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkToCrud('Ingrédients', 'fas fa-carrot', Ingredient::class);
        yield MenuItem::linkToCrud('Recettes', 'fas fa-utensils', Recette::class);
    }

    // Optional but recommended from previous steps
    public function configureActions(): Actions
    {
        return parent::configureActions()
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn(Action $action) => $action->setIcon('fa fa-edit')->setLabel(false))
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn(Action $action) => $action->setIcon('fa fa-trash')->setLabel(false))
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::EDIT, Action::DELETE]);
    }

    public function configureCrud(): Crud
    {
        return Crud::new()
            ->showEntityActionsInlined();
    }
}
