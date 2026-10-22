<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\Company;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Bookings\Models\Order;
use App\Modules\Finance\Models\PaymentAllocation;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RoyalModelsTest extends TestCase
{
    #[Test]
    public function models_map_to_real_royal_tables(): void
    {
        $this->assertSame('rt_companies', (new Company)->getTable());
        $this->assertSame('rt_orders', (new Order)->getTable());
        $this->assertSame('rt_bookings', (new Booking)->getTable());
        $this->assertSame('rt_payment_allocations', (new PaymentAllocation)->getTable());
    }

    #[Test]
    public function royal_models_are_mass_assignment_guarded(): void
    {
        $this->assertSame(['*'], (new Booking)->getGuarded());
    }
}
