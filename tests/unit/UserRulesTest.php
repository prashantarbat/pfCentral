<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\Users\UserRules;

/**
 * @internal
 */
final class UserRulesTest extends CIUnitTestCase
{
    public function testMobileAcceptsValidInternationalNumbers()
    {
        $this->assertSame('+15550192831', UserRules::mobile('+15550192831'));
        $this->assertSame('+447911123456', UserRules::mobile('+44 7911 123456'));
        $this->assertSame('+919876543210', UserRules::mobile('+91 9876543210'));
        $this->assertSame('+1234567890', UserRules::mobile('1234567890'));
    }

    public function testMobileRejectsInvalidNumbers()
    {
        $this->assertNull(UserRules::mobile('abc'));
        $this->assertNull(UserRules::mobile('123')); // too short (<7 digits)
        $this->assertNull(UserRules::mobile('12345678901234567890')); // too long (>15 digits)
    }

    public function testMobileHandlesEmptyInput()
    {
        $this->assertSame('', UserRules::mobile(''));
        $this->assertSame('', UserRules::mobile('   '));
    }

    public function testEmailValidation()
    {
        $this->assertSame('admin@pfcentral.local', UserRules::email('admin@pfcentral.local'));
        $this->assertSame('ops@company.org', UserRules::email(' ops@company.org '));
        $this->assertNull(UserRules::email('not-an-email'));
        $this->assertNull(UserRules::email('@missing-user.com'));
    }

    public function testUsernameDerivationFromEmail()
    {
        $this->assertSame('john.doe', UserRules::usernameFrom('john.doe@example.com'));
        $this->assertSame('admin', UserRules::usernameFrom('admin@company.net'));
        $this->assertSame('complex.user', UserRules::usernameFrom('complex+user@domain.com'));
    }

    public function testUsernameRules()
    {
        $this->assertSame('admin', UserRules::username('admin'));
        $this->assertSame('john.doe', UserRules::username('john.doe'));
        $this->assertNull(UserRules::username('ab')); // too short (<3)
        $this->assertNull(UserRules::username('user@domain')); // invalid special char @
    }

    public function testPasswordProblems()
    {
        $this->assertSame([], UserRules::passwordProblems('SecretPass123!', 'SecretPass123!', true));
        $this->assertContains('The two passwords do not match.', UserRules::passwordProblems('PassA', 'PassB', true));
        $this->assertContains('A password is required for a new account.', UserRules::passwordProblems('', '', true));
        $this->assertSame([], UserRules::passwordProblems('', '', false)); // not required during profile-only edit
    }
}
