<?php

namespace Tests\Unit;

use App\Enums\OrganizerInvitationStatus;
use PHPUnit\Framework\TestCase;

class OrganizerInvitationStatusTest extends TestCase
{
    public function test_has_expected_int_values(): void
    {
        $this->assertSame(0, OrganizerInvitationStatus::Pending->value);
        $this->assertSame(1, OrganizerInvitationStatus::Accepted->value);
        $this->assertSame(2, OrganizerInvitationStatus::Declined->value);
    }
}
