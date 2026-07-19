<?php

namespace App\Services\Security;

final class SensitiveDataSanitizer
{
    public function sanitize(string $value): string
    {
        $sanitized = preg_replace(
            '/-----BEGIN(?: [A-Z0-9]+)* PRIVATE KEY-----.*?-----END(?: [A-Z0-9]+)* PRIVATE KEY-----/is',
            '[PRIVATE KEY REDACTED]',
            $value,
        ) ?? '[REDACTED]';

        $sanitized = preg_replace(
            '/(?i)(password|passwd|passphrase|private-key|private_key|secret|token|api[-_]?key)\s*[=:]\s*("[^"]*"|\'[^\']*\'|[^\s;]+)/',
            '$1=[REDACTED]',
            $sanitized,
        ) ?? '[REDACTED]';

        $sanitized = preg_replace(
            '/(?i)(Authorization:\s*Bearer\s+)[^\s]+/',
            '$1[REDACTED]',
            $sanitized,
        ) ?? '[REDACTED]';

        return $sanitized;
    }

    public function excerpt(string $value, int $limit = 4000): string
    {
        return mb_substr($this->sanitize($value), 0, $limit);
    }
}
