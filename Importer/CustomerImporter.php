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
use App\Validator\ValidationFailedException;
use KimaiPlugin\ImportBundle\Model\ImportData;
use KimaiPlugin\ImportBundle\Model\ImportRow;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CustomerImporter implements ImporterInterface
{
    private $customerService;
    private $validator;

    public function __construct(CustomerService $customerService, ValidatorInterface $validator)
    {
        $this->customerService = $customerService;
        $this->validator = $validator;
    }

    public function supports(array $header): bool
    {
        $foundCustomer = false;
        $foundProject = false;
        $foundMore = false;

        foreach ($header as $column) {
            switch (strtolower($column)) {
                case 'company':
                case 'account':
                    $foundMore = true;
                    break;

                case 'project':
                    $foundProject = true;
                    break;

                case 'customer':
                    $foundCustomer = true;
                    break;
            }
        }

        return
            $foundCustomer && $foundMore ||
            $foundCustomer && !$foundProject
        ;
    }

    /**
     * @param array<ImportRow> $rows
     * @param bool $dryRun
     * @return ImportData
     */
    public function import(array $rows, bool $dryRun): ImportData
    {
        $data = new ImportData('customers', array_keys($rows[0]->getData()));

        foreach ($rows as $row) {
            try {
                $customer = $this->convertEntryToCustomer($row->getData());
                $this->validate($customer);
                if (!$dryRun) {
                    $this->customerService->saveNewCustomer($customer);
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

        return $data;
    }

    private function convertEntryToCustomer(array $entry): Customer
    {
        $name = null;

        foreach ($entry as $key => $value) {
            switch (strtolower($key)) {
                case 'customer':
                    if ($value !== null) {
                        $name = trim($value);
                    }
                    break;
            }
        }

        if ($name === null || $name === '') {
            throw new ImportException('Missing customer name');
        }

        if (mb_strlen($name) > 149) {
            throw new ImportException('Invalid customer name, maximum 150 character allowed');
        }

        $customer = $this->customerService->createNewCustomer();
        $customer->setName(mb_substr($name, 0, 149));

        $this->mapEntryToCustomer($customer, $entry);

        return $customer;
    }

    private function mapEntryToCustomer(Customer $customer, array $row): void
    {
        foreach ($row as $key => $value) {
            if ($value !== null) {
                $value = trim($value);
            }
            if ($value === '') {
                $value = null;
            }
            switch (strtolower($key)) {
                case 'company':
                    $customer->setCompany($value);
                    break;

                case 'email':
                    $customer->setEmail($value);
                    break;

                case 'country':
                    $customer->setCountry($value);
                    break;

                case 'account':
                    $customer->setNumber($value);
                    break;

                case 'tax':
                    $customer->setVatId($value);
                    break;

                case 'comment':
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
                    $customer->setCurrency($value);
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
                    $customer->setVisible((bool) $value);
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
