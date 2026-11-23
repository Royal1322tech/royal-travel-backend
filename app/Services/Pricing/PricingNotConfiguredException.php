<?php

namespace App\Services\Pricing;

use RuntimeException;

/** A required Royal or company pricing policy has no active version. Internal; never shown to clients. */
final class PricingNotConfiguredException extends RuntimeException
{
}
