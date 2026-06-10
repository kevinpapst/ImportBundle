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
        'projectnumber'
    ];

    private static array $propertyToColumns = [
        'name' => ['name', 'project'],
        'comment' => ['description'],
        'number' => ['projectnumber'],
        'orderNumber' => ['ordernumber'],
        'orderDate' => ['orderdate'],
        'start' => ['startdate'],
        'end' => ['enddate'],
        'budgetType' => ['budgettype'],
        'timeBudget' => ['timebudget'],
    ];

    /** @var Customer[] */
    private array $customerCache = [];
    /** @var Project[] */
    private array $projectCache = [];

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

        return $foundCustomer && $foundProject && !$foundTimesheet;
    }

    /**
     * @param array<ImportRow> $rows
     */
    public function import(ImportModelInterface $model, array $rows): ImportData
    {
        $data = new ImportData('projects', array_keys($rows[0]->getData()));

        // Pass 1: build + validate all rows — nothing is saved
        $projects = []; // [ImportRow, Project|null]
        $failureRows = 0;
        foreach ($rows as $row) {
            $project = null;
            try {
                $project = $this->convertEntryToProject($row->getData());
                $errors = $this->validator->validate($project);

                if ($errors->count() > 0) {
                    throw new ValidationFailedException($errors, 'Project validation failed in row ' . $row->getRowNumber());
                }

                /** @var Customer $customer */
                $customer = $project->getCustomer();
                if ($customer->isNew()) {
                    $errors = $this->validator->validate($customer);

                    if ($errors->count() > 0) {
                        throw new ValidationFailedException($errors, 'Customer validation failed in row ' . $row->getRowNumber());
                    }
                }

                $processedData = [];
                foreach ($row->getData() as $key => $rawValue) {
                    $normalizedKey = strtolower($key);
                    $processedData[$key] = match ($normalizedKey) {
                        'name', 'project' => $project->getName(),
                        'customer' => $customer->getName(),
                        'description' => $project->getComment(),
                        'color' => $project->getColor(),
                        'visible' => $project->isVisible(),
                        'budget' => $project->getBudget(),
                        'budgettype' => $project->getBudgetType(),
                        'timebudget' => $project->getTimeBudget(),
                        'ordernumber' => $project->getOrderNumber(),
                        'orderdate' => $project->getOrderDate(),
                        'startdate' => $project->getStart(),
                        'enddate' => $project->getEnd(),
                        'projectnumber' => $project->getNumber(),
                        default => $rawValue,
                    };
                }
                $row->setProcessedData($processedData);
            } catch (ImportException $exception) {
                $row->addError($exception->getMessage(), $exception->getField());
                $project = null;
                $failureRows++;
            } catch (ValidationFailedException $exception) {
                for ($i = 0; $i < $exception->getViolations()->count(); $i++) {
                    $violation = $exception->getViolations()->get($i);
                    $field = $this->resolveFieldKey($violation->getPropertyPath(), $row->getData());
                    $row->addError($violation->getMessage(), $field);
                }
                $project = null;
                $failureRows++;
            }
            $data->addRow($row);
            $projects[] = [$row, $project];
        }

        // Pass 2: save only when every row passed validation
        if (!$data->hasErrors()) {
            $createdCustomers = 0;
            $createdProjects = 0;
            $updatedProjects = 0;
            foreach ($projects as [$row, $project]) {
                if ($project !== null) {
                    /** @var Customer $customer */
                    $customer = $project->getCustomer();
                    if ($customer->isNew()) {
                        $this->customerService->saveCustomer($customer);
                        $createdCustomers++;
                    }
                    if ($project->isNew()) {
                        $createdProjects++;
                    } else {
                        $updatedProjects++;
                    }
                    $this->projectService->saveProject($project);
                }
            }
            if ($createdCustomers > 0) {
                $data->addStatus(\sprintf('created %s customers', $createdCustomers));
            }
            if ($createdProjects > 0) {
                $data->addStatus(\sprintf('created %s projects', $createdProjects));
            }
            if ($updatedProjects > 0) {
                $data->addStatus(\sprintf('updated %s projects', $updatedProjects));
            }
        } else {
            $data->addStatus(\sprintf('Found %s rows with errors', $failureRows));
        }

        return $data;
    }

    public function convertEntryToProject(array $entry): Project
    {
        $customer = $this->findCustomer($entry);

        $name = null;
        $nameColumn = 'name';
        foreach ($entry as $key => $value) {
            switch (strtolower($key)) {
                case 'name':
                case 'project':
                    $nameColumn = $key;
                    if ($value !== null) {
                        $name = trim($value);
                    }
                    break 2;
            }
        }

        if ($name === null || $name === '') {
            throw new ImportException('Cannot use empty project name', $nameColumn);
        }

        $cacheKey = $name . '_____' . $customer->getName();
        if (!\array_key_exists($cacheKey, $this->projectCache)) {
            $project = null;
            if (!$customer->isNew()) {
                $project = $this->projectService->findProjectByName($name, $customer);
            }
            if ($project === null) {
                $project = $this->projectService->createNewProject($customer);
                $project->setName($name);
            }
            $this->projectCache[$cacheKey] = $project;
        }

        $project = $this->projectCache[$cacheKey];
        $this->mapEntryToProject($project, $entry);

        return $project;
    }

    private function findCustomer(array $entry): Customer
    {
        $name = null;
        $nameColumn = 'customer';
        foreach ($entry as $key => $value) {
            switch (strtolower($key)) {
                case 'customer':
                    $nameColumn = $key;
                    if ($value !== null) {
                        $name = trim($value);
                    }
                    break 2;
            }
        }

        if ($name === null || $name === '') {
            throw new ImportException('Cannot use empty customer name', $nameColumn);
        }

        if (!\array_key_exists($name, $this->customerCache)) {
            $customer = $this->customerService->findCustomerByName($name);
            if ($customer === null) {
                if (mb_strlen($name) > 149) {
                    throw new ImportException('Invalid customer name, maximum 149 character allowed', 'customer');
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
            if (\is_array($value)) {
                continue;
            }
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
                    if ($value === null) {
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
                    if ($value === null) {
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
                    if ($value === null) {
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

                case 'projectnumber':
                    if (\is_string($value)) {
                        $project->setNumber(mb_substr($value, 0, 10));
                    }
                    break;

                default:
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
                    break;
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

    private function resolveFieldKey(string $propertyPath, array $csvRow): ?string
    {
        $csvKeysLower = array_map('strtolower', array_keys($csvRow));

        if (isset(self::$propertyToColumns[$propertyPath])) {
            foreach (self::$propertyToColumns[$propertyPath] as $candidate) {
                if (\in_array($candidate, $csvKeysLower, true)) {
                    return $candidate;
                }
            }
        }

        $propertyLower = strtolower($propertyPath);
        if (\in_array($propertyLower, $csvKeysLower, true)) {
            return $propertyLower;
        }

        return null;
    }
}
