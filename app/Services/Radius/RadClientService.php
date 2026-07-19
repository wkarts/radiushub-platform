<?php

namespace App\Services\Radius;

use RuntimeException;
use Symfony\Component\Process\Process;

class RadClientService
{
    public function send(string $host, int $port, string $type, string $secret, array $attributes): array
    {
        $binary = (string) config('radius.radclient_binary');
        $packet = collect($attributes)->map(fn($value, $key) => sprintf('%s = "%s"', $key, addcslashes((string)$value, "\\\"")))->implode("\n")."\n";
        $process = new Process([$binary, '-x', $host.':'.$port, $type, $secret]);
        $process->setInput($packet);
        $process->setTimeout((float) config('radius.timeout', 3) + 2);
        $process->run();
        $output = trim($process->getOutput()."\n".$process->getErrorOutput());
        if (! $process->isSuccessful()) throw new RuntimeException('radclient falhou: '.$output);
        return ['successful' => true, 'output' => $output, 'packet' => $packet];
    }
}
