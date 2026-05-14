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
        'customernumber',
        'budget_type',
        'time_budget',
        'customer_number',
        'number',
        'address_line1',
        'address_line2',
        'address_line3',
        'postcode',
        'city',
        'buyerreference',
        'buyer_reference',
    ];

    private static array $propertyToColumns = [
        'name' => ['name', 'customer'],
        'comment' => ['description'],
        'vatId' => ['tax'],
        'number' => ['customernumber', 'account', 'number', 'customer_number'],
        'buyerReference' => ['buyerreference', 'buyer_reference'],
        'addressLine1' => ['address_line1'],
        'addressLine2' => ['address_line2'],
        'addressLine3' => ['address_line3'],
        'postCode' => ['postcode'],
        'budgetType' => ['budgettype', 'budget_type'],
        'timeBudget' => ['timebudget', 'time_budget'],
    ];

    /** @var Customer[] */
    private array $customerCache = [];

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

        return $foundCustomer && !$foundProject;
    }

    /**
     * @param array<ImportRow> $rows
     */
    public function import(ImportModelInterface $model, array $rows): ImportData
    {
        $data = new ImportData('customers', array_keys($rows[0]->getData()));

        // Pass 1: build + validate all rows — nothing is saved
        $customers = []; // [ImportRow, Customer|null]
        $failureRows = 0;
        foreach ($rows as $row) {
            $customer = null;
            try {
                $customer = $this->convertEntryToCustomer($row->getData());
                $this->validate($customer);

                $processedData = [];
                foreach ($row->getData() as $key => $rawValue) {
                    $normalizedKey = strtolower($key);
                    $processedData[$key] = match ($normalizedKey) {
                        'name', 'customer' => $customer->getName(),
                        'company' => $customer->getCompany(),
                        'email' => $customer->getEmail(),
                        'country' => $customer->getCountry(),
                        'currency' => $customer->getCurrency(),
                        'timezone' => $customer->getTimezone(),
                        'phone' => $customer->getPhone(),
                        'mobile' => $customer->getMobile(),
                        'fax' => $customer->getFax(),
                        'homepage' => $customer->getHomepage(),
                        'address' => $customer->getAddress(),
                        'address_line1' => $customer->getAddressLine1(),
                        'address_line2' => $customer->getAddressLine2(),
                        'address_line3' => $customer->getAddressLine3(),
                        'postcode' => $customer->getPostCode(),
                        'city' => $customer->getCity(),
                        'contact' => $customer->getContact(),
                        'description' => $customer->getComment(),
                        'color' => $customer->getColor(),
                        'visible' => $customer->isVisible(),
                        'budget' => $customer->getBudget(),
                        'budgettype', 'budget_type' => $customer->getBudgetType(),
                        'timebudget', 'time_budget' => $customer->getTimeBudget(),
                        'customernumber', 'account', 'number', 'customer_number' => $customer->getNumber(),
                        'tax' => $customer->getVatId(),
                        'buyerreference', 'buyer_reference' => $customer->getBuyerReference(),
                        default => $rawValue,
                    };
                }
                $row->setProcessedData($processedData);
            } catch (ImportException $exception) {
                $row->addError($exception->getMessage(), $exception->getField());
                $customer = null;
                $failureRows++;
            } catch (ValidationFailedException $exception) {
                for ($i = 0; $i < $exception->getViolations()->count(); $i++) {
                    $violation = $exception->getViolations()->get($i);
                    $field = $this->resolveFieldKey($violation->getPropertyPath(), $row->getData());
                    $row->addError($violation->getMessage(), $field);
                }
                $customer = null;
                $failureRows++;
            }
            $data->addRow($row);
            $customers[] = [$row, $customer];
        }

        // Pass 2: save only when every row passed validation
        if (!$data->hasErrors()) {
            $createdCustomers = 0;
            $updatedCustomers = 0;
            foreach ($customers as [$row, $customer]) {
                if ($customer !== null) {
                    if ($customer->isNew()) {
                        $createdCustomers++;
                    } else {
                        $updatedCustomers++;
                    }
                    $this->customerService->saveCustomer($customer);
                }
            }
            if ($createdCustomers > 0) {
                $data->addStatus(\sprintf('created %s customers', $createdCustomers));
            }
            if ($updatedCustomers > 0) {
                $data->addStatus(\sprintf('updated %s customers', $updatedCustomers));
            }
        } else {
            $data->addStatus(\sprintf('Found %s rows with errors', $failureRows));
        }

        return $data;
    }

    private function convertEntryToCustomer(array $entry): Customer
    {
        $name = null;
        $nameColumn = 'name';

        foreach ($entry as $key => $value) {
            switch (strtolower($key)) {
                case 'name':
                case 'customer':
                    $nameColumn = $key;
                    if ($value !== null) {
                        $name = trim($value);
                    }
                    break 2;
            }
        }

        if ($name === null || $name === '') {
            throw new ImportException('Missing customer name', $nameColumn);
        }

        if (mb_strlen($name) > 149) {
            throw new ImportException('Invalid customer name, maximum 150 character allowed', $nameColumn);
        }

        $name = mb_substr($name, 0, 149);

        $cacheKey = $name;
        if (!\array_key_exists($cacheKey, $this->customerCache)) {
            $customer = $this->customerService->findCustomerByName($name);
            if ($customer === null) {
                $customer = $this->customerService->createNewCustomer($name);
            }
            $this->customerCache[$cacheKey] = $customer;
        }

        $customer = $this->customerCache[$cacheKey];
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
                case 'number':
                case 'customer_number':
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
                case 'budget_type':
                    $customer->setBudgetType($value);
                    break;

                case 'timebudget':
                case 'time_budget':
                    $customer->setTimeBudget((int) $value);
                    break;

                case 'address_line1':
                    $customer->setAddressLine1($value);
                    break;

                case 'address_line2':
                    $customer->setAddressLine2($value);
                    break;

                case 'address_line3':
                    $customer->setAddressLine3($value);
                    break;

                case 'postcode':
                    $customer->setPostCode($value);
                    break;

                case 'city':
                    $customer->setCity($value);
                    break;

                case 'buyerreference':
                case 'buyer_reference':
                    $customer->setBuyerReference($value);
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

    private function validate(Customer $value): void
    {
        $errors = $this->validator->validate($value);

        if ($errors->count() > 0) {
            throw new ValidationFailedException($errors, 'Validation Failed');
        }
    }
}
