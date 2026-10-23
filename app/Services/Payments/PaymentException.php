<?php

namespace App\Services\Payments;

use RuntimeException;

/**
 * A payment provider refused or failed a request. The message is safe to show to users.
 */
class PaymentException extends RuntimeException {}
