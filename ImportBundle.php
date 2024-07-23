<?php

/*
 * This file is part of the "Import bundle" for Kimai.
 * All rights reserved by Kevin Papst (www.kevinpapst.de).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ImportBundle;

use App\Plugin\PluginInterface;
use App\Validator\Constraints\TimesheetDeactivated;
use App\Validator\Constraints\TimesheetLockdown;
use App\Validator\Constraints\TimesheetLongRunning;
use App\Validator\Constraints\TimesheetZeroDuration;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class ImportBundle extends Bundle implements PluginInterface
{
    // some validator rules that we skip during import, because they are not relevant here
    // do not use the class name directly, because that would raise the required kimai version

    public const SKIP_VALIDATOR_CODES = [
        'kimai-timesheet-87', 'kimai-timesheet-88', 'kimai-timesheet-89', // timesheet deactivated before 2.0.16
        'kimai-timesheet-deactivated-activity', 'kimai-timesheet-deactivated-project', 'kimai-timesheet-deactivated-customer', // timesheet deactivated after 2.0.16
        TimesheetDeactivated::DISABLED_ACTIVITY_ERROR, TimesheetDeactivated::DISABLED_PROJECT_ERROR, TimesheetDeactivated::DISABLED_CUSTOMER_ERROR, // timesheet deactivated current codes
        TimesheetLockdown::PERIOD_LOCKED, // lockdown is obviously not necessary to check here
        TimesheetLongRunning::LONG_RUNNING, TimesheetLongRunning::MAXIMUM, TimesheetZeroDuration::ZERO_DURATION_ERROR, // if this was allowed in the past, it should be imported
    ];
}
