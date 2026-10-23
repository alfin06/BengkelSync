<?php

namespace App\Exceptions;

use DomainException;

/**
 * A rule of the business was broken by a user action (e.g. "the last owner cannot be
 * deactivated"). The message is already translated and is shown to the user as a
 * flash error; see bootstrap/app.php.
 */
class BusinessRuleViolation extends DomainException {}
