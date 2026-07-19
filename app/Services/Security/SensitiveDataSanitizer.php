<?php

namespace App\Services\Security;

final class SensitiveDataSanitizer
{
    public function sanitize(string $value): string
    {
        $patterns = [
            '/(?i)(password|passwd|passphrase|private-key|secret|token)\s*[=:]\s*("[^"]*"|[^\s;]+)/',
            '/-----BEGIN [^-]+ PRIVATE KEY-----.*?-----END [^-]+ PRIVATE KEY-----/s',
            '/(?i)(Authorization:\s*Bearer\s+)[^\s]+/',
        ];

        return preg_replace($patterns, '$1=[REDACTED]', $value) ?? '[REDACTED]';
    }

    public function excerpt(string $value, int $limit = 4000): string
    {
        return mb_substr($this->sanitize($value), 0, $limit);
    }
}
