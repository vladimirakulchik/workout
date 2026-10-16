<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use PHPUnit\Framework\TestCase;

class UserEnumsTest extends TestCase
{
    public function test_roles_have_expected_values_and_labels(): void
    {
        $this->assertSame(
            ['super_admin', 'trainer', 'coach', 'player'],
            array_map(fn (UserRole $role) => $role->value, UserRole::cases()),
        );
        $this->assertSame('Super Admin', UserRole::SuperAdmin->label());
        $this->assertSame('Player', UserRole::Player->label());
    }

    public function test_statuses_have_expected_values_and_labels(): void
    {
        $this->assertSame(
            ['active', 'inactive', 'deleted'],
            array_map(fn (UserStatus $status) => $status->value, UserStatus::cases()),
        );
        $this->assertSame('Inactive', UserStatus::Inactive->label());
    }
}
