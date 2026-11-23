<?php

namespace App\Services\Pricing;

use RuntimeException;

/** No current reference exchange rate for the requested display currency. Internal. */
final class FxRateUnavailableException extends RuntimeException
{
}
