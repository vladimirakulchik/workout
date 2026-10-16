<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class SessionConfigTest extends TestCase
{
    public function test_link_expiry_is_configured_explicitly(): void
    {
        $this->assertSame(1440, config('auth.verification.expire'));
        $this->assertSame(60, config('auth.passwords.users.expire'));
    }

    public function test_session_lifetime_defaults_to_seven_days_on_the_database_driver(): void
    {
        $config = file_get_contents(config_path('session.php'));

        $this->assertStringContainsString("env('SESSION_LIFETIME', 10080)", $config);
        $this->assertStringContainsString("env('SESSION_DRIVER', 'database')", $config);
    }
}
