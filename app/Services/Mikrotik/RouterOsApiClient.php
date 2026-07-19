<?php

namespace App\Services\Mikrotik;

use RuntimeException;

class RouterOsApiClient
{
    /** @var resource|null */
    private $socket = null;

    public function connect(string $host, int $port, bool $ssl, string $username, string $password, int $timeout = 5): void
    {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ]);

        $target = ($ssl ? 'tls' : 'tcp').'://'.$host.':'.$port;
        $errno = 0;
        $error = '';
        $this->socket = @stream_socket_client($target, $errno, $error, $timeout, STREAM_CLIENT_CONNECT, $context);

        if (! is_resource($this->socket)) {
            throw new RuntimeException("Falha ao conectar ao RouterOS: {$error} ({$errno})");
        }

        stream_set_timeout($this->socket, $timeout);
        $this->command('/login', ['name' => $username, 'password' => $password]);
    }

    public function command(string $command, array $attributes = [], array $queries = []): array
    {
        if (! is_resource($this->socket)) {
            throw new RuntimeException('Conexão RouterOS não inicializada.');
        }

        $words = [$command];

        foreach ($attributes as $key => $value) {
            $words[] = '='.$key.'='.(is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value);
        }

        foreach ($queries as $query) {
            $words[] = '?'.$query;
        }

        $this->writeSentence($words);
        $reply = $this->readReply();
        $this->assertNoTrap($reply);

        return $reply;
    }

    public function close(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }

        $this->socket = null;
    }

    public function __destruct()
    {
        $this->close();
    }

    private function writeSentence(array $words): void
    {
        foreach ($words as $word) {
            $this->writeWord((string) $word);
        }

        $this->writeWord('');
    }

    private function writeWord(string $word): void
    {
        $data = $this->encodeLength(strlen($word)).$word;
        $written = 0;

        while ($written < strlen($data)) {
            $result = fwrite($this->socket, substr($data, $written));

            if ($result === false || $result === 0) {
                throw new RuntimeException('Falha ao escrever no socket RouterOS.');
            }

            $written += $result;
        }
    }

    private function readReply(): array
    {
        $sentences = [];
        $sentence = [];

        while (true) {
            $word = $this->readWord();

            if ($word === '') {
                if ($sentence === []) {
                    continue;
                }

                $sentences[] = $sentence;
                $type = $sentence[0] ?? '';
                $sentence = [];

                if (in_array($type, ['!done', '!fatal'], true)) {
                    break;
                }

                continue;
            }

            $sentence[] = $word;
        }

        return array_map(fn (array $words): array => $this->normalizeSentence($words), $sentences);
    }

    private function normalizeSentence(array $words): array
    {
        $result = ['type' => array_shift($words)];

        foreach ($words as $word) {
            if (str_starts_with($word, '=')) {
                [, $key, $value] = array_pad(explode('=', $word, 3), 3, '');
                $result[$key] = $value;
            } else {
                $result[] = $word;
            }
        }

        return $result;
    }

    private function assertNoTrap(array $reply): void
    {
        foreach ($reply as $sentence) {
            if (in_array($sentence['type'] ?? '', ['!trap', '!fatal'], true)) {
                throw new RuntimeException($sentence['message'] ?? 'RouterOS retornou erro.');
            }
        }
    }

    private function readWord(): string
    {
        $length = $this->decodeLength();

        if ($length === 0) {
            return '';
        }

        return $this->readBytes($length);
    }

    private function encodeLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        if ($length < 0x4000) {
            $length |= 0x8000;
            return chr(($length >> 8) & 0xFF).chr($length & 0xFF);
        }

        if ($length < 0x200000) {
            $length |= 0xC00000;
            return chr(($length >> 16) & 0xFF).chr(($length >> 8) & 0xFF).chr($length & 0xFF);
        }

        if ($length < 0x10000000) {
            $length |= 0xE0000000;
            return pack('N', $length);
        }

        return chr(0xF0).pack('N', $length);
    }

    private function decodeLength(): int
    {
        $c = ord($this->readBytes(1));

        if (($c & 0x80) === 0x00) return $c;
        if (($c & 0xC0) === 0x80) return (($c & 0x3F) << 8) + ord($this->readBytes(1));
        if (($c & 0xE0) === 0xC0) return (($c & 0x1F) << 16) + (ord($this->readBytes(1)) << 8) + ord($this->readBytes(1));
        if (($c & 0xF0) === 0xE0) return (($c & 0x0F) << 24) + (ord($this->readBytes(1)) << 16) + (ord($this->readBytes(1)) << 8) + ord($this->readBytes(1));
        if (($c & 0xF8) === 0xF0) return unpack('N', $this->readBytes(4))[1];

        throw new RuntimeException('Comprimento inválido recebido da API RouterOS.');
    }

    private function readBytes(int $length): string
    {
        $data = '';

        while (strlen($data) < $length) {
            $chunk = fread($this->socket, $length - strlen($data));

            if ($chunk === false || $chunk === '') {
                $meta = is_resource($this->socket) ? stream_get_meta_data($this->socket) : [];
                $reason = ($meta['timed_out'] ?? false) ? 'tempo de leitura esgotado' : 'conexão encerrada';
                throw new RuntimeException('Resposta incompleta da API RouterOS: '.$reason.'.');
            }

            $data .= $chunk;
        }

        return $data;
    }
}
