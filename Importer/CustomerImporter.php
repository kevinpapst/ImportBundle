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
use App\Entity\CustomerMeta;
use App\Validator\ValidationFailedException;
use KimaiPlugin\ImportBundle\Model\ImportData;
use KimaiPlugin\ImportBundle\Model\ImportModelInterface;
use KimaiPlugin\ImportBundle\Model\ImportRow;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CustomerImporter implements ImporterInterface
{
    /**
     * @var string[]
     */
    private static array $supportedHeader = [
        'name',
        'customer',
        'company',
        'email',
        'country',
        'account',
        'tax',
        'description',
        'address',
        'contact',
        'currency',
        'timezone',
        'phone',
        'mobile',
        'fax',
        'homepage',
        'color',
        'visible',
        'budget',
        'budgettype',
        'timebudget',
        'customernumber'
    ];

    public function __construct(
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

        foreach ($header as $column) {
            switch (strtolower($column)) {
                case 'project':
                    $foundProject = true;
                    break;

                case 'name':
                case 'customer':
                    $foundCustomer = true;
                    break;
            }
        }

        return
            $foundCustomer && !$foundProject
        ;
    }

    /**
     * @param array<ImportRow> $rows
     */
    public function import(ImportModelInterface $model, array $rows): ImportData
    {
        $dryRun = $model->isPreview();
        $data = new ImportData('customers', array_keys($rows[0]->getData()));

        $createdCustomers = 0;
        $updatedCustomers = 0;

        foreach ($rows as $row) {
            try {
                $customer = $this->convertEntryToCustomer($row->getData());
                $this->validate($customer);

                if ($customer->isNew()) {
                    $createdCustomers++;
                } else {
                    $updatedCustomers++;
                }

                if (!$dryRun) {
                    $this->customerService->saveCustomer($customer);
                }
            } catch (ImportException $exception) {
                $row->addError($exception->getMessage());
            } catch (ValidationFailedException $exception) {
                for ($i = 0; $i < $exception->getViolations()->count(); $i++) {
                    $row->addError($exception->getViolations()->get($i)->getMessage());
                }
            }

            $data->addRow($row);
        }

        if ($createdCustomers > 0) {
            $data->addStatus(\sprintf('created %s customers', $createdCustomers));
        }

        if ($updatedCustomers > 0) {
            $data->addStatus(\sprintf('updated %s customers', $updatedCustomers));
        }

        return $data;
    }

    private function convertEntryToCustomer(array $entry): Customer
    {
        $name = null;

        foreach ($entry as $key => $value) {
            switch (strtolower($key)) {
                case 'name':
                case 'customer':
                    if ($value !== null) {
                        $name = trim($value);
                    }
                    break 2;
            }
        }

        if ($name === null || $name === '') {
            throw new ImportException('Missing customer name');
        }

        if (mb_strlen($name) > 149) {
            throw new ImportException('Invalid customer name, maximum 150 character allowed');
        }

        $name = mb_substr($name, 0, 149);

        // allow updating existing customers
        $customer = $this->customerService->findCustomerByName($name);
        if ($customer === null) {
            $customer = $this->customerService->createNewCustomer($name);
        }

        $this->mapEntryToCustomer($customer, $entry);

        return $customer;
    }

    private function mapEntryToCustomer(Customer $customer, array $row): void
    {
        foreach ($row as $key => $value) {
            if (\is_array($value)) {
                continue;
            }
            if ($value !== null) {
                $value = trim($value);
            }
            if ($value === '') {
                $value = null;
            }
            $key = strtolower($key);
            switch ($key) {
                case 'company':
                    $customer->setCompany($value);
                    break;

                case 'email':
                    $customer->setEmail($value);
                    break;

                case 'country':
                    $customer->setCountry($value);
                    break;

                case 'customernumber':
                case 'account':
                    if (\is_string($value)) {
                        $customer->setNumber($value);
                    }
                    break;

                case 'tax':
                    $customer->setVatId($value);
                    break;

                case 'description':
                    $customer->setComment($value);
                    break;

                case 'address':
                    $customer->setAddress($value);
                    break;

                case 'contact':
                    $customer->setContact($value);
                    break;

                case 'currency':
                    if ($value !== null) {
                        $customer->setCurrency($value);
                    }
                    break;

                case 'timezone':
                    if ($value !== null) {
                        $customer->setTimezone($value);
                    }
                    break;

                case 'phone':
                    $customer->setPhone($value);
                    break;

                case 'mobile':
                    $customer->setMobile($value);
                    break;

                case 'fax':
                    $customer->setFax($value);
                    break;

                case 'homepage':
                    $customer->setHomepage($value);
                    break;

                case 'color':
                    $customer->setColor($value);
                    break;

                case 'visible':
                    $customer->setVisible(ImporterHelper::convertBoolean($value));
                    break;

                case 'budget':
                    $customer->setBudget((float) $value);
                    break;

                case 'budgettype':
                    $customer->setBudgetType($value);
                    break;

                case 'timebudget':
                    $customer->setTimeBudget((int) $value);
                    break;

                default:
                    if (str_starts_with($key, 'meta.')) {
                        $metaName = str_replace('meta.', '', $key);
                        $meta = $customer->getMetaField($metaName);
                        if ($meta === null) {
                            $meta = new CustomerMeta();
                            $meta->setName($metaName);
                        }
                        $meta->setValue($value);
                        $customer->setMetaField($meta);
                    }
                    break;
            }
        }
    }

    private function validate(Customer $value): void
    {
        $errors = $this->validator->validate($value);

        if ($errors->count() > 0) {
            throw new ValidationFailedException($errors, 'Validation Failed');
        }
    }
}
