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
use KimaiPlugin\ImportBundle\Form\ImportForm;
use KimaiPlugin\ImportBundle\Importer\ImporterService;
use KimaiPlugin\ImportBundle\Importer\ImportException;
use KimaiPlugin\ImportBundle\Model\ImportModel;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(path="/importer")
 * @Security("is_granted('importer')")
 */
final class ImportController extends AbstractController
{
    /**
     * @Route(path="/", name="importer", methods={"GET", "POST"})
     */
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

        return $this->render('@Import/index.html.twig', [
            'model' => $model,
            'data' => $data,
            'form' => $editForm->createView()
        ]);
    }

    /**
     * @Route(path="/example/customer", name="importer_example_customer", methods={"GET"})
     */
    public function demoCustomer(): Response
    {
        return $this->file(__DIR__ . '/../Resources/demo/customer.csv');
    }

    /**
     * @Route(path="/example/project", name="importer_example_project", methods={"GET"})
     */
    public function demoProject(): Response
    {
        return $this->file(__DIR__ . '/../Resources/demo/project.csv');
    }

    /**
     * @Route(path="/example/grandtotal", name="importer_example_grandtotal", methods={"GET"})
     */
    public function demoGrandtotal(): Response
    {
        return $this->file(__DIR__ . '/../Resources/demo/grandtotal.csv');
    }
}
