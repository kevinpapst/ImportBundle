<?php

/*
 * This file is part of the "Import bundle" for Kimai.
 * All rights reserved by Kevin Papst (www.kevinpapst.de).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ImportBundle\Importer;

use App\Activity\ActivityService;
use App\Customer\CustomerService;
use App\Entity\Activity;
use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\Tag;
use App\Entity\Timesheet;
use App\Entity\TimesheetMeta;
use App\Entity\User;
use App\Project\ProjectService;
use App\Repository\ProjectRepository;
use App\Repository\TagRepository;
use App\Timesheet\TimesheetService;
use App\User\UserService;
use App\Utils\Duration;
use App\Validator\ValidationFailedException;
use KimaiPlugin\ImportBundle\ImportBundle;
use KimaiPlugin\ImportBundle\Model\ImportData;
use KimaiPlugin\ImportBundle\Model\ImportModelInterface;
use KimaiPlugin\ImportBundle\Model\ImportRow;
use KimaiPlugin\ImportBundle\Model\TimesheetImportModel;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractTimesheetImporter
{
    /** @var Customer[] */
    private array $customerCache = [];
    /** @var Project[] */
    private array $projectCache = [];
    /** @var Activity[] */
    private array $activityCache = [];
    /** @var array<User|null> */
    private array $userCache = [];
    /** @var Tag[] */
    private array $tagCache = [];

    private int $createdUsers = 0;
    private int $createdProjects = 0;
    private int $createdCustomers = 0;
    private int $createdActivities = 0;
    private int $createdTags = 0;
    private bool $globalActivity = true;

    public function __construct(
        private readonly CustomerService $customerService,
        private readonly ProjectService $projectService,
        private readonly ProjectRepository $projectRepository,
        private readonly ActivityService $activityService,
        private readonly UserService $userService,
        private readonly TagRepository $tagRepository,
        private readonly TimesheetService $timesheetService,
        protected readonly TranslatorInterface $translator,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * Build and validate one timesheet row (no saves). Subclasses override this to
     * handle format-specific transformations and call parent::importRow() with a
     * normalised ImportRow.
     *
     * Returns the built Timesheet on success, null on validation error.
     * Always adds the row to $data.
     */
    public function importRow(Duration $durationParser, ImportData $data, ImportRow $row): ?Timesheet
    {
        $timesheet = null;
        try {
            /** @var array<string, bool|string|int> $record */
            $record = $row->getData();

            foreach ($this->collectFieldErrors($record) as $field => $message) {
                $row->addError($message, $field);
            }
            if ($row->hasError()) {
                $data->addRow($row);

                return null;
            }

            $userIdentifier = $record['User'];
            $userName = \array_key_exists('Username', $record) ? $record['Username'] : $userIdentifier;

            if (null === ($user = $this->getUser((string) $userIdentifier, (string) $record['Email'], (string) $userName))) {
                throw new ImportException(\sprintf('Unknown user %s', (string) $userIdentifier), 'User');
            }

            $project = $this->getProject((string) $record['Project'], (string) $record['Customer']);
            $activity = $this->getActivity((string) $record['Activity'], $project);

            $duration = 0;
            $foundDuration = null;

            if (\array_key_exists('Duration', $record)) {
                if (\is_int($record['Duration'])) {
                    $duration = $record['Duration'];
                    $foundDuration = $duration;
                } elseif (\is_string($record['Duration']) && \strlen($record['Duration']) > 0) {
                    $duration = $this->parseDuration($durationParser, $record['Duration']);
                    $foundDuration = $duration;
                }
            }

            $timezone = new \DateTimeZone($user->getTimezone());

            try {
                $begin = new \DateTime((string) $record['Begin'], $timezone);
            } catch (\Exception $exception) {
                throw new ImportException($exception->getMessage(), 'Begin');
            }
            try {
                $end = new \DateTime((string) $record['End'], $timezone);
            } catch (\Exception $exception) {
                throw new ImportException($exception->getMessage(), 'End');
            }

            // fix dates running over midnight
            if ($end < $begin) {
                if ($duration > 0) {
                    $end = (new \DateTime())->setTimezone($timezone)->setTimestamp($begin->getTimestamp() + $duration);
                } else {
                    $end->add(new \DateInterval('P1D'));
                }
            }

            $timesheet = $this->timesheetService->createNewTimesheet($user);
            $this->timesheetService->prepareNewTimesheet($timesheet);

            $timesheet->setActivity($activity);
            $timesheet->setProject($project);
            $timesheet->setBegin($begin);
            $timesheet->setEnd($end);

            if ($foundDuration !== null) {
                $timesheet->setDuration($foundDuration);
            } else {
                $timesheet->setDuration($timesheet->getCalculatedDuration());
            }

            foreach ($record as $key => $value) {
                $k = strtolower($key);
                switch ($k) {
                    case 'description':
                        $timesheet->setDescription($value !== null ? (string) $value : null);
                        break;

                    case 'exported':
                        $timesheet->setExported(ImporterHelper::convertBoolean($value));
                        break;

                    case 'billable':
                        $timesheet->setBillable(ImporterHelper::convertBoolean($value));
                        break;

                    case 'break':
                        $timesheet->setBreak($this->parseDuration($durationParser, (string) $value));
                        break;

                    case 'rate':
                        if (is_numeric($value)) {
                            $timesheet->setRate((float) $value);
                        }
                        break;

                    case 'hourlyrate':
                        if (is_numeric($value)) {
                            $timesheet->setHourlyRate((float) $value);
                        }
                        break;

                    case 'fixedrate':
                        if (is_numeric($value)) {
                            $timesheet->setFixedRate((float) $value);
                        }
                        break;

                    case 'internalrate':
                        if (is_numeric($value)) {
                            $timesheet->setInternalRate((float) $value);
                        }
                        break;

                    case 'tags':
                        if (\is_string($value)) {
                            foreach (explode(',', $value) as $tagName) {
                                if ($tagName === '') {
                                    continue;
                                }
                                $timesheet->addTag($this->getTag($tagName));
                            }
                        }
                        break;

                    default:
                        if (str_starts_with($k, 'meta.')) {
                            $metaName = substr($k, 5);
                            $meta = $timesheet->getMetaField($metaName);
                            if ($meta === null) {
                                $meta = new TimesheetMeta();
                                $meta->setName($metaName);
                            }
                            $meta->setValue($value);
                            $timesheet->setMetaField($meta);
                        }
                        break;
                }
            }

            $errors = $this->validator->validate($timesheet);
            if ($errors->count() > 0) {
                throw new ValidationFailedException($errors, 'Timesheet validation failed in row ' . $row->getRowNumber());
            }

            // Build processedData keyed by same fields as $record so column count matches the header.
            $processedData = [];
            foreach ($record as $key => $rawValue) {
                $processedData[$key] = match (strtolower($key)) {
                    'begin' => $timesheet->getBegin(),
                    'end' => $timesheet->getEnd(),
                    'duration' => $timesheet->getDuration(),
                    'user' => $user->getUserIdentifier(),
                    'username' => $user->getUserIdentifier(),
                    'email' => $user->getEmail(),
                    'customer' => $project->getCustomer()?->getName(),
                    'project' => $project->getName(),
                    'activity' => $activity->getName(),
                    'description' => $timesheet->getDescription(),
                    'exported' => $timesheet->isExported(),
                    'billable' => $timesheet->isBillable(),
                    'rate' => $timesheet->getRate(),
                    'hourlyrate' => $timesheet->getHourlyRate(),
                    'fixedrate' => $timesheet->getFixedRate(),
                    'internalrate' => $timesheet->getInternalRate(),
                    'break' => $timesheet->getBreak(),
                    'tags' => implode(', ', $timesheet->getTagsAsArray()),
                    default => str_starts_with(strtolower($key), 'meta.')
                        ? $timesheet->getMetaField(substr(strtolower($key), 5))?->getValue()
                        : $rawValue,
                };
            }
            $row->setProcessedData($processedData);
        } catch (ImportException $exception) {
            $row->addError($exception->getMessage(), $exception->getField());
            $timesheet = null;
        } catch (ValidationFailedException $exception) {
            for ($i = 0; $i < $exception->getViolations()->count(); $i++) {
                $violation = $exception->getViolations()->get($i);
                $row->addError($violation->getMessage(), $violation->getPropertyPath());
            }
            $timesheet = null;
        }

        $data->addRow($row);

        return $timesheet;
    }

    protected function parseDuration(Duration $durationParser, string $duration): int
    {
        if (is_numeric($duration) && !str_contains($duration, '.') && !str_contains($duration, ',')) {
            return (int) $duration;
        }

        return $durationParser->parseDurationString($duration);
    }

    abstract protected function createImportData(ImportRow $row): ImportData;

    /**
     * @param array<ImportRow> $rows
     */
    public function import(ImportModelInterface $model, array $rows): ImportData
    {
        if (!$model instanceof TimesheetImportModel) {
            throw new ImportException('Invalid import model given, expected TimesheetImportModel');
        }

        $data = $this->createImportData($rows[0]);
        $this->timesheetService->setIgnoreValidationCodes(ImportBundle::SKIP_VALIDATOR_CODES);
        $durationParser = new Duration();
        $this->globalActivity = $model->isGlobalActivities();

        // Pass 1: build + validate all rows — nothing is saved
        $timesheets = [];
        $failureRows = 0;
        foreach ($rows as $row) {
            $timesheet = $this->importRow($durationParser, $data, $row);
            if ($timesheet !== null) {
                $timesheets[] = $timesheet;
            } else {
                $failureRows++;
            }
        }

        // Pass 2: save only when every row passed validation
        if ($data->hasErrors()) {
            $data->addStatus(\sprintf('Found %s rows with errors', $failureRows));
        } elseif (\count($timesheets) === 0) {
            $data->addStatus('Found no rows to import');
        } else {
            // Save in dependency order: tags → customers → projects → activities → users → timesheets
            foreach ($this->tagCache as $tag) {
                if ($tag->getId() === null) {
                    $this->tagRepository->saveTag($tag);
                    $this->createdTags++;
                }
            }
            foreach ($this->customerCache as $customer) {
                if ($customer->isNew()) {
                    $this->customerService->saveCustomer($customer);
                    $this->createdCustomers++;
                }
            }
            foreach ($this->projectCache as $project) {
                if ($project->isNew()) {
                    $this->projectService->saveProject($project);
                    $this->createdProjects++;
                }
            }
            foreach ($this->activityCache as $activity) {
                if ($activity->isNew()) {
                    $this->activityService->saveActivity($activity);
                    $this->createdActivities++;
                }
            }
            foreach ($this->userCache as $user) {
                if ($user !== null && $user->getId() === null) {
                    $this->userService->saveUser($user);
                    $this->createdUsers++;
                }
            }
            foreach ($timesheets as $timesheet) {
                $this->timesheetService->saveTimesheet($timesheet);
            }
        }

        if (($rowCount = $data->countRows()) > 0) {
            $data->addStatus(\sprintf('processed %s rows', $rowCount));
        }
        if (($errors = $data->countErrors()) > 0) {
            $data->addStatus(\sprintf('failed %s rows', $errors));
        }
        if ($this->createdCustomers > 0) {
            $data->addStatus(\sprintf('created %s customers', $this->createdCustomers));
        }
        if ($this->createdProjects > 0) {
            $data->addStatus(\sprintf('created %s projects', $this->createdProjects));
        }
        if ($this->createdActivities > 0) {
            $data->addStatus(\sprintf('created %s activities', $this->createdActivities));
        }
        if ($this->createdTags > 0) {
            $data->addStatus(\sprintf('created %s tags', $this->createdTags));
        }
        if ($this->createdUsers > 0) {
            $data->addStatus(\sprintf('created %s users', $this->createdUsers));
        }

        return $data;
    }

    private function getUser(string $user, string $email, string $alias): ?User
    {
        if (!\array_key_exists($user, $this->userCache)) {
            $tmpUser = $this->userService->findUserByEmail($email);
            if ($tmpUser === null) {
                $tmpUser = $this->userService->findUserByName($user);
            }
            if ($tmpUser === null) {
                $tmpUser = $this->userService->createNewUser();
                $tmpUser->setAlias($alias);
                $tmpUser->setEmail($email);
                $tmpUser->setUserIdentifier($user);
                $tmpUser->setPlainPassword(uniqid());

                $errors = $this->validator->validate($tmpUser, null, ['Registration', 'UserCreate']);
                if ($errors->count() > 0) {
                    throw new ValidationFailedException($errors, 'User validation failed for email: ' . $email);
                }
                // saved in pass 2
            }
            $this->userCache[$user] = $tmpUser;
        }

        return $this->userCache[$user];
    }

    private function getTag(string $tagName): Tag
    {
        $normalizedTagName = trim(mb_substr($tagName, 0, 100));

        if (!\array_key_exists($normalizedTagName, $this->tagCache)) {
            $tag = $this->tagRepository->findTagByName($normalizedTagName);
            if ($tag === null) {
                $tag = new Tag();
                $tag->setName($normalizedTagName);
                // saved in pass 2
            }
            $this->tagCache[$normalizedTagName] = $tag;
        }

        return $this->tagCache[$normalizedTagName];
    }

    private function getActivity(string $activity, Project $project): Activity
    {
        $cacheKey = $this->globalActivity
            ? $activity . '_____GLOBAL_____'
            : $activity . '_____' . $project->getId();

        if (!\array_key_exists($cacheKey, $this->activityCache)) {
            $tmpActivity = $this->globalActivity
                ? $this->activityService->findActivityByName($activity, null)
                : $this->activityService->findActivityByName($activity, $project);

            if (null === $tmpActivity) {
                $tmpActivity = $this->activityService->createNewActivity($this->globalActivity ? null : $project);
                $tmpActivity->setName($activity);
                // saved in pass 2
            }

            $this->activityCache[$cacheKey] = $tmpActivity;
        }

        return $this->activityCache[$cacheKey];
    }

    private function getProject(string $project, string $customer): Project
    {
        $cacheKey = $project . '_____' . $customer;

        if (!\array_key_exists($cacheKey, $this->projectCache)) {
            $tmpCustomer = $this->getCustomer($customer);
            $tmpProject = $tmpCustomer->isNew()
                ? null
                : $this->projectRepository->findOneBy(['name' => $project, 'customer' => $tmpCustomer->getId()]);

            if ($tmpProject === null) {
                $tmpProject = $this->projectService->createNewProject($tmpCustomer);
                $tmpProject->setName($project);
                // saved in pass 2
            }

            $this->projectCache[$cacheKey] = $tmpProject;
        }

        return $this->projectCache[$cacheKey];
    }

    private function getCustomer(string $customer): Customer
    {
        if (!\array_key_exists($customer, $this->customerCache)) {
            $tmpCustomer = $this->customerService->findCustomerByName($customer);
            if ($tmpCustomer === null) {
                $tmpCustomer = $this->customerService->createNewCustomer($customer);
                // saved in pass 2
            }
            $this->customerCache[$customer] = $tmpCustomer;
        }

        return $this->customerCache[$customer];
    }

    /**
     * Returns field-level validation errors for a normalised record row.
     * Keys are field names matching the ImportData header; values are error messages.
     *
     * @param array<string, string|int|null|bool> $row
     * @return array<string, string>
     */
    private function collectFieldErrors(array $row): array
    {
        $errors = [];
        $empty = 'Empty or missing field';
        $encoding = 'Invalid encoding, requires UTF-8';
        $negative = 'Negative values not supported';
        $float = 'Invalid numeric value';

        if (!\array_key_exists('User', $row) || $row['User'] === null || $row['User'] === '') {
            $errors['User'] = $empty;
        }
        if (!\array_key_exists('Email', $row)) {
            $errors['Email'] = 'Missing email address';
        }
        if (filter_var($row['Email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['Email'] = 'Invalid email address';
        }
        if (!\array_key_exists('Begin', $row) || $row['Begin'] === null || $row['Begin'] === '') {
            $errors['Begin'] = $empty;
        }
        if (!\array_key_exists('End', $row) || $row['End'] === null || $row['End'] === '') {
            $errors['End'] = $empty;
        }
        if (!isset($row['Project']) || $row['Project'] === '') {
            $errors['Project'] = $empty;
        } elseif (\is_string($row['Project']) && mb_detect_encoding($row['Project'], 'UTF-8', true) !== 'UTF-8') {
            $errors['Project'] = $encoding;
        }
        if (!isset($row['Activity']) || $row['Activity'] === '') {
            $errors['Activity'] = $empty;
        } elseif (\is_string($row['Activity']) && mb_detect_encoding($row['Activity'], 'UTF-8', true) !== 'UTF-8') {
            $errors['Activity'] = $encoding;
        }
        if (\array_key_exists('Duration', $row)) {
            $dur = $row['Duration'];
            if (\is_string($dur) && $dur !== '' && $dur[0] === '-') {
                $errors['Duration'] = $negative;
            } elseif (\is_int($dur) && $dur < 0) {
                $errors['Duration'] = $negative;
            }
        }
        if (\array_key_exists('Description', $row) && \is_string($row['Description']) && $row['Description'] !== '' && mb_detect_encoding($row['Description'], 'UTF-8', true) !== 'UTF-8') {
            $errors['Description'] = $encoding;
        }
        if (\array_key_exists('HourlyRate', $row) && $row['HourlyRate'] !== null && $row['HourlyRate'] !== '' && !is_numeric($row['HourlyRate'])) {
            $errors['HourlyRate'] = $float;
        }
        if (\array_key_exists('InternalRate', $row) && $row['InternalRate'] !== null && $row['InternalRate'] !== '' && !is_numeric($row['InternalRate'])) {
            $errors['InternalRate'] = $float;
        }
        if (\array_key_exists('FixedRate', $row) && $row['FixedRate'] !== null && $row['FixedRate'] !== '' && !is_numeric($row['FixedRate'])) {
            $errors['FixedRate'] = $float;
        }

        return $errors;
    }
}
