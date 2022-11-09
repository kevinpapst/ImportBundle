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
use App\Utils\FileHelper;
use App\Utils\PageSetup;
use KimaiPlugin\ImportBundle\Form\ImportForm;
use KimaiPlugin\ImportBundle\Importer\ImporterService;
use KimaiPlugin\ImportBundle\Importer\ImportException;
use KimaiPlugin\ImportBundle\Model\ImportModel;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/importer')]
#[Security("is_granted('importer')")]
final class ImportController extends AbstractController
{
    #[Route(path: '/', name: 'importer', methods: ['GET', 'POST'])]
    public function importer(Request $request, FileHelper $fileHelper, ImporterService $importerService): Response
    {
        $model = new ImportModel();
        $editForm = $this->createForm(ImportForm::class, $model, [
            'action' => $this->generateUrl('importer'),
            'method' => 'POST',
        ]);
        $data = null;

        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            try {
                $data = $importerService->import($model);
            } catch (ImportException $importException) {
                $editForm->addError(new FormError($importException->getMessage()));
            }
        }

        $page = new PageSetup('Importer');
        $page->setHelp('plugin-import.html');

        return $this->render('@Import/index.html.twig', [
            'page_setup' => $page,
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
        }

        throw $this->createNotFoundException('Unknown demo file');
    }
}
