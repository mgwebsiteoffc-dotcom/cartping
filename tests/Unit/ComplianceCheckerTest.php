<?php

namespace Tests\Unit;

use App\Services\Templates\ComplianceChecker;
use PHPUnit\Framework\TestCase;

class ComplianceCheckerTest extends TestCase
{
    protected ComplianceChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new ComplianceChecker();
    }

    public function test_detects_deceptive_phrases_as_blocking_errors(): void
    {
        $issues = $this->checker->check('You have won a free prize! Claim now.');
        $this->assertTrue($this->checker->hasBlockingErrors($issues));
        $this->assertContains('banned_phrase', array_column($issues, 'code'));
    }

    public function test_clean_template_has_no_blocking_errors(): void
    {
        $issues = $this->checker->check('Hi {{1}}, your order #{{2}} has shipped. Track it here: {{3}}', [
            'category' => 'UTILITY',
        ]);

        $this->assertFalse($this->checker->hasBlockingErrors($issues));
    }

    public function test_detects_sensitive_pii(): void
    {
        $issues = $this->checker->check('Please reply with your CVV number and PIN to verify.');
        $this->assertTrue($this->checker->hasBlockingErrors($issues));
    }
}
