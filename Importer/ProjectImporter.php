<?php

/*
 * This file is part of the "Import bundle" for Kimai.
 * All rights reserved by Kevin Papst (www.kevinpapst.de).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ImportBundle\Importer;

use App\Customer\CustomerService;
use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\ProjectMeta;
use App\Project\ProjectService;
use App\Validator\ValidationFailedException;
use KimaiPlugin\ImportBundle\Model\ImportData;
use KimaiPlugin\ImportBundle\Model\ImportModel;
use KimaiPlugin\ImportBundle\Model\ImportModelInterface;
use KimaiPlugin\ImportBundle\Model\ImportRow;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ProjectImporter implements ImporterInterface
{
    /**
     * @var string[]
     */
    private static array $supportedHeader = [
        'name',
        'project',
        'customer',
        'exported',
        'duration',
        'user',
        'description',
        'orderdate',
        'user',
        'startdate',
        'color',
        'visible',
        'budget',
        'timebudget',
        'budgettype',
    ];

    /**
     * @var Customer[]
     */
    private array $customerCache = [];

    public function __construct(
        private readonly ProjectService $projectService,
        private readonly CustomerService $customerService,
        private readonly ValidatorInterface $validator
    )
    {
    }

    public function checkHeader(array $header): array
    {
        return array_diff(self::$supportedHeader, $header);
    }

    public function supports(array $header): bool
    {
        $foundCustomer = false;
        $foundProject = false;
        $foundTimesheet = false;

        foreach ($header as $column) {
            switch (strtolower($column)) {
                case 'name':
                case 'project':
                    $foundProject = true;
                    break;

                case 'customer':
                    $foundCustomer = true;
                    break;

                case 'exported':
                case 'duration':
                case 'user':
                    $foundTimesheet = true;
                    break;
            }
        }

        return
            $foundCustomer && $foundProject && !$foundTimesheet
        ;
    }

    /**
     * @param ImportModel $model
     * @param array<ImportRow> $rows
     * @return ImportData
     */
    public function import(ImportModelInterface $model, array $rows): ImportData
    {
        $dryRun = $model->isPreview();
        $data = new ImportData('projects', array_keys($rows[0]->getData()));

        $createdCustomer = 0;
        $createdProject = 0;

        foreach ($rows as $row) {
            try {
                $project = $this->convertEntryToProject($row->getData());
                $this->validate($project);
                /** @var Customer $customer */
                $customer = $project->getCustomer();
                if ($customer->getId() === null) {
                    $this->validate($customer);
                }
                if ($customer->getId() === null) {
                    $createdCustomer++;
                }
                if (!$dryRun) {
                    if ($customer->getId() === null) {
                        $this->customerService->saveNewCustomer($customer);
                    }
                    $this->projectService->saveNewProject($project);
                }
                $createdProject++;
            } catch (ImportException $exception) {
                $row->addError($exception->getMessage());
            } catch (ValidationFailedException $exception) {
                for ($i = 0; $i < $exception->getViolations()->count(); $i++) {
                    $row->addError($exception->getViolations()->get($i)->getMessage());
                }
            }
            $data->addRow($row);
        }

        if ($createdCustomer > 0) {
            $data->addStatus('created ' . $createdCustomer . ' customers');
        }

        if ($createdProject > 0) {
            $data->addStatus('created ' . $createdProject . ' projects');
        }

        return $data;
    }

    public function convertEntryToProject(array $entry): Project
    {
        $customer = $this->findCustomer($entry);

        $name = null;
        foreach ($entry as $key => $value) {
            switch (strtolower($key)) {
                case 'name':
                case 'project':
                    if ($value !== null) {
                        $name = trim($value);
                    }
                    break 2;
            }
        }

        if ($name === null || $name === '') {
            throw new ImportException('Cannot use empty project name');
        }

        $project = $this->projectService->createNewProject($customer);
        $project->setName($name);

        $this->mapEntryToProject($project, $entry);

        return $project;
    }

    private function findCustomer(array $entry): Customer
    {
        $name = null;
        foreach ($entry as $key => $value) {
            switch (strtolower($key)) {
                case 'customer':
                    if ($value !== null) {
                        $name = trim($value);
                    }
                    break 2;
            }
        }

        if ($name === null || $name === '') {
            throw new ImportException('Cannot use empty customer name');
        }

        if (!\array_key_exists($name, $this->customerCache)) {
            $customer = $this->customerService->findCustomerByName($name);

            if ($customer === null) {
                if (mb_strlen($name) > 149) {
                    throw new ImportException('Invalid customer name, maximum 150 character allowed');
                }

                $customer = $this->customerService->createNewCustomer(mb_substr($name, 0, 149));
            }

            $this->customerCache[$name] = $customer;
        }

        return $this->customerCache[$name];
    }

    private function mapEntryToProject(Project $project, array $row): void
    {
        foreach ($row as $name => $value) {
            if ($value !== null) {
                $value = trim($value);
            }
            if ($value === '') {
                $value = null;
            }
            $key = strtolower($name);
            switch ($key) {
                case 'description':
                    $project->setComment($value);
                    break;

                case 'ordernumber':
                    if ($value !== null) {
                        $project->setOrderNumber(mb_substr($value, 0, 50));
                    } else {
                        $project->setOrderNumber(null);
                    }
                    break;

                case 'orderdate':
                    if (empty($value)) {
                        $project->setOrderDate(null);
                    } else {
                        $newDate = $this->mapDateTime($project, $value);
                        if ($project->getOrderDate() === null || $project->getOrderDate()->getTimestamp() !== $newDate->getTimestamp()) {
                            $newDate->setTime(0, 0, 0);
                            $project->setOrderDate($newDate);
                        }
                    }
                    break;

                case 'startdate':
                    if (empty($value)) {
                        $project->setStart(null);
                    } else {
                        $newDate = $this->mapDateTime($project, $value);
                        if ($project->getStart() === null || $project->getStart()->getTimestamp() !== $newDate->getTimestamp()) {
                            $newDate->setTime(0, 0, 0);
                            $project->setStart($newDate);
                        }
                    }
                    break;

                case 'enddate':
                    if (empty($value)) {
                        $project->setEnd(null);
                    } else {
                        $newDate = $this->mapDateTime($project, $value);
                        if ($project->getEnd() === null || $project->getEnd()->getTimestamp() !== $newDate->getTimestamp()) {
                            $newDate->setTime(0, 0, 0);
                            $project->setEnd($newDate);
                        }
                    }
                    break;

                case 'color':
                    $project->setColor($value);
                    break;

                case 'visible':
                    $project->setVisible(ImporterHelper::convertBoolean($value));
                    break;

                case 'budget':
                    $project->setBudget((float) $value);
                    break;

                case 'budgettype':
                    $project->setBudgetType($value);
                    break;

                case 'timebudget':
                    $project->setTimeBudget((int) $value);
                    break;
            }

            if (str_starts_with($key, 'meta.')) {
                $metaName = str_replace('meta.', '', $key);
                $meta = $project->getMetaField($metaName);
                if ($meta === null) {
                    $meta = new ProjectMeta();
                    $meta->setName($metaName);
                }
                $meta->setValue($value);
                $project->setMetaField($meta);
            }
        }
    }

    private function mapDateTime(Project $project, string $value): \DateTime
    {
        $timezone = date_default_timezone_get();
        if ($project->getCustomer() !== null && $project->getCustomer()->getTimezone() !== null) {
            $timezone = $project->getCustomer()->getTimezone();
        }

        try {
            $date = \DateTime::createFromFormat('Y-m-d', $value, new \DateTimeZone($timezone));
        } catch (\Exception $exception) {
            throw new \InvalidArgumentException($exception->getMessage());
        }

        if ($date === false) {
            throw new \InvalidArgumentException('Invalid order date: ' . $value);
        }

        return $date;
    }

    private function validate(Project|Customer $value): void
    {
        $errors = $this->validator->validate($value);

        if ($errors->count() > 0) {
            throw new ValidationFailedException($errors, 'Validation Failed');
        }
    }
}
