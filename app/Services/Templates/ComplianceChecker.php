<?php

namespace App\Services\Templates;

/**
 * Static WhatsApp template compliance checks (both Meta Business policy and
 * common sense rules). Returns issues as a list of {code, severity, message}.
 *
 * severity: error (blocks submission) | warning (advisory)
 */
class ComplianceChecker
{
    protected const BANNED = [
        'guarantee', 'guaranteed', '100% off', 'free money', 'click here to win',
        'you have won', 'congratulations you', 'act now or', 'limited time only!!',
        'get rich', 'no risk', 'cash prize', 'lottery',
    ];

    protected const URL_STRINGS = [
        'http://', 'https://', 'www.', '.com', '.net', '.io',
    ];

    public function check(string $body, array $context = []): array
    {
        $issues = [];
        $lower = mb_strtolower($body);

        foreach (self::BANNED as $phrase) {
            if (str_contains($lower, $phrase)) {
                $issues[] = $this->issue('error', 'banned_phrase', "Contains potentially deceptive phrase: \"{$phrase}\"");
            }
        }

        // Meta requires accurate, non-misleading content.
        if (preg_match('/\b(urgent|asap|immediately|last chance)\b/', $lower)) {
            $issues[] = $this->issue('warning', 'pressure', 'Avoid high-pressure language that may read as spam.');
        }

        if (str_contains($lower, '!!') && mb_substr_count($lower, '!') > 2) {
            $issues[] = $this->issue('warning', 'excessive_punctuation', 'Excessive exclamation marks look spammy.');
        }

        if (! preg_match('/[a-z]{20,}/', $lower)) {
            $issues[] = $this->issue('warning', 'length', 'Template body is very short; ensure it carries enough value.');
        }

        $hasPlaceholder = preg_match('/\{\{\d\}\}/', $body);
        if (! $hasPlaceholder && ($context['category'] ?? null) === 'MARKETING') {
            $issues[] = $this->issue('warning', 'no_variable', 'No {{1}} variable defined; personalisation improves approval odds.');
        }

        // Personal info rules.
        if (preg_match('/(\b\d{4}\s*[- ]?\d{4}\s*[- ]?\d{4}\b|\bcvv\b|\bpassword\b|\bpin\b)/i', $body)) {
            $issues[] = $this->issue('error', 'pii', 'Template appears to request or expose sensitive payment/credential data.');
        }

        return $issues;
    }

    public function hasBlockingErrors(array $issues): bool
    {
        return collect($issues)->contains(fn ($i) => ($i['severity'] ?? '') === 'error');
    }

    public function isUrlPresent(string $body): bool
    {
        foreach (self::URL_STRINGS as $needle) {
            if (str_contains($body, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected function issue(string $severity, string $code, string $message): array
    {
        return compact('severity', 'code', 'message');
    }
}
