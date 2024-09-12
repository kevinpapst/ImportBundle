<?php

/*
 * This file is part of the "Import bundle" for Kimai.
 * All rights reserved by Kevin Papst (www.kevinpapst.de).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ImportBundle\Controller;

use App\Controller\AbstractController;
use App\Utils\PageSetup;
use KimaiPlugin\ImportBundle\Form\ImportForm;
use KimaiPlugin\ImportBundle\Form\TimesheetImportForm;
use KimaiPlugin\ImportBundle\Importer\ClockifyTimesheetImporter;
use KimaiPlugin\ImportBundle\Importer\CustomerImporter;
use KimaiPlugin\ImportBundle\Importer\GrandtotalCustomerImporter;
use KimaiPlugin\ImportBundle\Importer\ImporterService;
use KimaiPlugin\ImportBundle\Importer\ImportException;
use KimaiPlugin\ImportBundle\Importer\ProjectImporter;
use KimaiPlugin\ImportBundle\Importer\TimesheetImporter;
use KimaiPlugin\ImportBundle\Importer\TogglTimesheetImporter;
use KimaiPlugin\ImportBundle\Model\ImportModel;
use KimaiPlugin\ImportBundle\Model\TimesheetImportModel;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/importer')]
#[IsGranted('importer')]
final class ImportController extends AbstractController
{
    #[Route(path: '/', name: 'importer', methods: ['GET', 'POST'])]
    #[Route(path: '/timesheet', name: 'importer_timesheet', methods: ['GET', 'POST'])]
    public function timesheet(Request $request, ImporterService $importerService): Response
    {
        return $this->showForm($request, new TimesheetImportModel(), TimesheetImportForm::class, 'timesheet', 'importer_timesheet', TimesheetImporter::class, $importerService);
    }

    #[Route(path: '/customer', name: 'importer_customer', methods: ['GET', 'POST'])]
    public function customer(Request $request, ImporterService $importerService): Response
    {
        return $this->showForm($request, new ImportModel(), ImportForm::class, 'customer', 'importer_customer', CustomerImporter::class, $importerService);
    }

    #[Route(path: '/project', name: 'importer_project', methods: ['GET', 'POST'])]
    public function project(Request $request, ImporterService $importerService): Response
    {
        return $this->showForm($request, new ImportModel(), ImportForm::class, 'project', 'importer_project', ProjectImporter::class, $importerService);
    }

    #[Route(path: '/grandtotal', name: 'importer_grandtotal', methods: ['GET', 'POST'])]
    public function grandtotal(Request $request, ImporterService $importerService): Response
    {
        return $this->showForm($request, new ImportModel(), ImportForm::class, 'grandtotal', 'importer_grandtotal', GrandtotalCustomerImporter::class, $importerService);
    }

    #[Route(path: '/clockify', name: 'importer_clockify', methods: ['GET', 'POST'])]
    public function clockify(Request $request, ImporterService $importerService): Response
    {
        return $this->showForm($request, new TimesheetImportModel(), TimesheetImportForm::class, 'clockify', 'importer_clockify', ClockifyTimesheetImporter::class, $importerService);
    }

    #[Route(path: '/toggl', name: 'importer_toggl', methods: ['GET', 'POST'])]
    public function toggl(Request $request, ImporterService $importerService): Response
    {
        $model = new TimesheetImportModel();
        $model->setDelimiter(',');

        return $this->showForm($request, $model, TimesheetImportForm::class, 'toggl', 'importer_toggl', TogglTimesheetImporter::class, $importerService);
    }

    /**
     * @param class-string<FormTypeInterface<ImportModel>> $formClass
     * @param class-string $importer
     */
    private function showForm(Request $request, ImportModel $model, string $formClass, string $tab, string $route, string $importer, ImporterService $importerService): Response
    {
        $editForm = $this->createForm($formClass, $model, [
            'action' => $this->generateUrl($route),
            'method' => 'POST',
        ]);
        $data = null;

        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            try {
                $data = $importerService->import($model, $importer);
            } catch (ImportException $importException) {
                $editForm->addError(new FormError($importException->getMessage()));
            }
        }

        $page = new PageSetup('Importer');
        $page->setHelp('plugin-import.html');

        return $this->render('@Import/index.html.twig', [
            'page_setup' => $page,
            'tab' => $tab,
            'model' => $model,
            'data' => $data,
            'form' => $editForm->createView()
        ]);
    }

    #[Route(path: '/example/customer-csv', name: 'importer_example_customer_csv', methods: ['GET'])]
    #[Route(path: '/example/customer-json', name: 'importer_example_customer_json', methods: ['GET'])]
    #[Route(path: '/example/project-csv', name: 'importer_example_project_csv', methods: ['GET'])]
    #[Route(path: '/example/project-json', name: 'importer_example_project_json', methods: ['GET'])]
    #[Route(path: '/example/timesheet-csv', name: 'importer_example_timesheet_csv', methods: ['GET'])]
    #[Route(path: '/example/timesheet-json', name: 'importer_example_timesheet_json', methods: ['GET'])]
    #[Route(path: '/example/grandtotal', name: 'importer_example_grandtotal', methods: ['GET'])]
    #[Route(path: '/example/clockify', name: 'importer_example_clockify', methods: ['GET'])]
    #[Route(path: '/example/toggl', name: 'importer_example_toggl', methods: ['GET'])]
    public function demoFiles(Request $request): Response
    {
        switch ($request->get('_route')) {
            case 'importer_example_customer_csv':
                return $this->file(__DIR__ . '/../Resources/demo/customer.csv');

            case 'importer_example_customer_json':
                return $this->file(__DIR__ . '/../Resources/demo/customer.json');

            case 'importer_example_project_csv':
                return $this->file(__DIR__ . '/../Resources/demo/project.csv');

            case 'importer_example_project_json':
                return $this->file(__DIR__ . '/../Resources/demo/project.json');

            case 'importer_example_timesheet_csv':
                return $this->file(__DIR__ . '/../Resources/demo/timesheet.csv');

            case 'importer_example_timesheet_json':
                return $this->file(__DIR__ . '/../Resources/demo/timesheet.json');

            case 'importer_example_grandtotal':
                return $this->file(__DIR__ . '/../Resources/demo/grandtotal.csv');

            case 'importer_example_clockify':
                return $this->file(__DIR__ . '/../Resources/demo/clockify.csv');

            case 'importer_example_toggl':
                return $this->file(__DIR__ . '/../Resources/demo/toggl.csv');
        }

        throw $this->createNotFoundException('Unknown demo file');
    }
}
