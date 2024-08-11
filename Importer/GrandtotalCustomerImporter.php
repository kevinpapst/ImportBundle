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
use KimaiPlugin\ImportBundle\Model\ImportModel;
use KimaiPlugin\ImportBundle\Model\ImportModelInterface;
use KimaiPlugin\ImportBundle\Model\ImportRow;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class GrandtotalCustomerImporter implements ImporterInterface
{
    /**
     * @var string[]
     */
    private static array $supportedHeader = [
        'department',
        'abteilung',
        'salutation',
        'briefanrede',
        'state',
        'bundesland',
        'iban',
        'bic',
        'sepa mandate id',
        'sepa mandat',
        'organization',
        'firma',
        'e-mail',
        'country',
        'land',
        'customer number',
        'kundennummer',
        'tax-id',
        'umsatzsteuer',
        'note',
        'notiz',
        'title',
        'titel',
        'first name',
        'vorname',
        'middle name',
        'zweiter vorname',
        'last name',
        'nachname',
        'street',
        'straße',
        'zip',
        'plz',
        'city',
        'ort',
    ];

    public function __construct(private CustomerService $customerService, private ValidatorInterface $validator)
    {
    }

    public function checkHeader(array $header): array
    {
        return array_diff(self::$supportedHeader, $header);
    }

    public function supports(array $header): bool
    {
        $foundCustomer = false;

        foreach ($header as $column) {
            switch (strtolower($column)) {
                case 'organization':
                case 'firma':
                    $foundCustomer = true;
                    break;
            }
        }

        return
            $foundCustomer
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
        $data = new ImportData('importer.grandtotal', array_keys($rows[0]->getData()));

        $createdCustomers = 0;

        foreach ($rows as $row) {
            try {
                $customer = $this->convertEntryToCustomer($row->getData());
                $this->validate($customer);
                if (!$dryRun) {
                    $this->customerService->saveNewCustomer($customer);
                }
                $createdCustomers++;
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

        return $data;
    }

    private function convertEntryToCustomer(array $entry): Customer
    {
        $name = null;

        foreach ($entry as $key => $value) {
            switch (strtolower($key)) {
                case 'organization':
                case 'firma':
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

        $customer = $this->customerService->createNewCustomer(mb_substr($name, 0, 149));

        $this->mapEntryToCustomer($customer, $entry);

        return $customer;
    }

    private function mapEntryToCustomer(Customer $customer, array $row): void
    {
        $names = ['first' => '', 'middle' => '', 'last' => '', 'title' => ''];
        $address = ['street' => '',  'city' => '', 'code' => ''];

        foreach ($row as $name => $value) {
            if ($value !== null) {
                $value = trim($value);
            }
            if ($value === '') {
                $value = null;
            }
            switch (strtolower($name)) {
                case 'department':
                case 'abteilung':

                case 'salutation':
                case 'briefanrede':

                case 'state':
                case 'bundesland':

                case 'iban':
                case 'bic':

                case 'sepa mandate id':
                case 'sepa mandat':
                    // not supported in Kimai
                    break;

                case 'organization':
                case 'firma':
                    // used as customer name
                    break;

                case 'e-mail':
                    $customer->setEmail($value);
                    break;

                case 'country':
                case 'land':
                    $customer->setCountry($value);
                    break;

                case 'customer number':
                case 'kundennummer':
                    $customer->setNumber($value);
                    break;

                case 'tax-id':
                case 'umsatzsteuer':
                    $customer->setVatId($value);
                    break;

                case 'note':
                case 'notiz':
                    if ($value !== null) {
                        $value = strip_tags($value);
                    }
                    $customer->setComment($value);
                    break;

                case 'title':
                case 'titel':
                    $names['title'] = $value;
                    break;

                case 'first name':
                case 'vorname':
                    $names['first'] = $value;
                    break;

                case 'middle name':
                case 'zweiter vorname':
                    $names['middle'] = $value;
                    break;

                case 'last name':
                case 'nachname':
                    $names['last'] = $value;
                    break;

                case 'street':
                case 'straße':
                    $address['street'] = $value;
                    break;

                case 'zip':
                case 'plz':
                    $address['code'] = $value;
                    break;

                case 'city':
                case 'ort':
                    $address['city'] = $value;
                    break;
            }
        }

        $calculatedAddress = $address['street'] . PHP_EOL . $address['code'] . ' ' . $address['city'];
        $calculatedContact = $names['title'] . ' ' . $names['first'] . ' ' . $names['middle'] . ' ' . $names['last'];

        if ($address['street'] !== '' || $address['code'] !== '' || $address['city'] !== '') {
            $customer->setAddress(trim($calculatedAddress));
        }

        if ($names['title'] !== '' || $names['first'] !== '' || $names['middle'] !== '' || $names['last'] !== '') {
            $customer->setContact(trim(str_replace('  ', ' ', $calculatedContact)));
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
