<?php

namespace App\Services\Mikrotik;

use InvalidArgumentException;

final class RouterOsCommandBuilder
{
    public function build(string $key, array $parameters = []): string
    {
        return match ($key) {
            'identity' => '/system identity print terse without-paging; /system resource print terse without-paging; /system routerboard print terse without-paging',
            'health' => '/system resource print terse without-paging; /system package print terse without-paging',
            'hotspot-users' => '/ip hotspot user print detail without-paging',
            'ppp-secrets' => '/ppp secret print detail without-paging',
            'hotspot-profiles' => '/ip hotspot user profile print detail without-paging',
            'ppp-profiles' => '/ppp profile print detail without-paging',
            'active-sessions' => '/ip hotspot active print detail without-paging; /ppp active print detail without-paging',
            'sync-profile' => $this->syncProfile($parameters),
            'sync-access' => $this->syncAccess($parameters),
            'disable-access' => $this->disableAccess($parameters),
            'sync-voucher' => $this->syncVoucher($parameters),
            'disable-voucher' => $this->disableVoucher($parameters),
            'disconnect-session' => $this->disconnectSession($parameters),
            'set-session-rate-limit' => $this->setSessionRateLimit($parameters),
            default => throw new InvalidArgumentException("Comando SSH não autorizado: {$key}"),
        };
    }

    public function safeReadKeys(): array
    {
        return ['identity', 'health', 'hotspot-users', 'ppp-secrets', 'hotspot-profiles', 'ppp-profiles', 'active-sessions'];
    }

    public function allowedKeys(): array
    {
        return [
            'identity', 'health', 'hotspot-users', 'ppp-secrets', 'hotspot-profiles',
            'ppp-profiles', 'active-sessions', 'sync-profile', 'sync-access',
            'disable-access', 'sync-voucher', 'disable-voucher', 'disconnect-session',
            'set-session-rate-limit',
        ];
    }

    private function syncProfile(array $p): string
    {
        $name = $this->required($p, 'name');
        $service = $p['service_type'] ?? 'hotspot';
        $rate = $this->value($p['rate_limit'] ?? '');
        $session = max(0, (int) ($p['session_timeout_seconds'] ?? 0));
        $shared = max(1, (int) ($p['max_devices'] ?? 1));

        if ($service === 'both') {
            return $this->syncProfile(array_replace($p, ['service_type' => 'hotspot']))
                .'; '.$this->syncProfile(array_replace($p, ['service_type' => 'pppoe']));
        }

        if ($service === 'pppoe') {
            return ':local i [/ppp profile find where name='.$this->quote($name).']; '
                .':if ([:len $i]=0) do={/ppp profile add name='.$this->quote($name).' rate-limit='.$this->quote($rate).'} '
                .'else={/ppp profile set $i rate-limit='.$this->quote($rate).'}';
        }

        return ':local i [/ip hotspot user profile find where name='.$this->quote($name).']; '
            .':if ([:len $i]=0) do={/ip hotspot user profile add name='.$this->quote($name)
            .' rate-limit='.$this->quote($rate).' shared-users='.$shared
            .($session > 0 ? ' session-timeout='.$session.'s' : '').'} '
            .'else={/ip hotspot user profile set $i rate-limit='.$this->quote($rate)
            .' shared-users='.$shared.($session > 0 ? ' session-timeout='.$session.'s' : '').'}';
    }

    private function syncAccess(array $p): string
    {
        $service = $p['service_type'] ?? 'hotspot';
        $username = $this->required($p, 'username');
        $password = $this->required($p, 'password');
        $profile = $this->value($p['profile'] ?? 'default');
        $callerId = $this->value($p['caller_id'] ?? '');
        $disabled = ($p['disabled'] ?? false) ? 'yes' : 'no';

        if ($service === 'both') {
            return $this->syncAccess(array_replace($p, ['service_type' => 'hotspot']))
                .'; '.$this->syncAccess(array_replace($p, ['service_type' => 'pppoe']));
        }

        if ($service === 'pppoe') {
            return ':local i [/ppp secret find where name='.$this->quote($username).']; '
                .':if ([:len $i]=0) do={/ppp secret add name='.$this->quote($username)
                .' password='.$this->quote($password).' profile='.$this->quote($profile)
                .' service=pppoe disabled='.$disabled.'} else={/ppp secret set $i password='
                .$this->quote($password).' profile='.$this->quote($profile).' service=pppoe disabled='.$disabled.'}';
        }

        return ':local i [/ip hotspot user find where name='.$this->quote($username).']; '
            .':if ([:len $i]=0) do={/ip hotspot user add name='.$this->quote($username)
            .' password='.$this->quote($password).' profile='.$this->quote($profile)
            .($callerId !== '' ? ' mac-address='.$this->quote($callerId) : '')
            .' disabled='.$disabled.'} else={/ip hotspot user set $i password='.$this->quote($password)
            .' profile='.$this->quote($profile).($callerId !== '' ? ' mac-address='.$this->quote($callerId) : '')
            .' disabled='.$disabled.'}';
    }

    private function disableAccess(array $p): string
    {
        $username = $this->required($p, 'username');
        $service = $p['service_type'] ?? 'hotspot';

        if ($service === 'both') {
            return $this->disableAccess(array_replace($p, ['service_type' => 'hotspot']))
                .'; '.$this->disableAccess(array_replace($p, ['service_type' => 'pppoe']));
        }

        return $service === 'pppoe'
            ? '/ppp secret set [find where name='.$this->quote($username).'] disabled=yes'
            : '/ip hotspot user set [find where name='.$this->quote($username).'] disabled=yes';
    }

    private function syncVoucher(array $p): string
    {
        return $this->syncAccess([
            'service_type' => 'hotspot',
            'username' => $this->required($p, 'code'),
            'password' => $this->required($p, 'password'),
            'profile' => $p['profile'] ?? 'default',
            'disabled' => $p['disabled'] ?? false,
        ]);
    }

    private function disableVoucher(array $p): string
    {
        return $this->disableAccess(['service_type' => 'hotspot', 'username' => $this->required($p, 'code')]);
    }


    private function disconnectSession(array $p): string
    {
        $username = $this->required($p, 'username');
        $address = $this->optionalIp($p['framed_ip_address'] ?? null);
        $queueName = $this->sessionQueueName($p['session_id'] ?? $username);

        $hotspotFind = 'user='.$this->quote($username)
            .($address ? ' and address='.$this->quote($address) : '');
        $pppFind = 'name='.$this->quote($username)
            .($address ? ' and address='.$this->quote($address) : '');

        return ':local h [/ip hotspot active find where '.$hotspotFind.']; '
            .':if ([:len $h]>0) do={/ip hotspot active remove $h}; '
            .':local p [/ppp active find where '.$pppFind.']; '
            .':if ([:len $p]>0) do={/ppp active remove $p}; '
            .':local q [/queue simple find where name='.$this->quote($queueName).']; '
            .':if ([:len $q]>0) do={/queue simple remove $q}';
    }

    private function setSessionRateLimit(array $p): string
    {
        $address = $this->requiredIp($p, 'framed_ip_address');
        $rateLimit = $this->requiredRateLimit($p, 'rate_limit');
        $queueName = $this->sessionQueueName($p['session_id'] ?? $address);
        $target = $address.'/32';

        return ':local q [/queue simple find where name='.$this->quote($queueName).']; '
            .':if ([:len $q]=0) do={/queue simple add name='.$this->quote($queueName)
            .' target='.$this->quote($target).' max-limit='.$this->quote($rateLimit)
            .' comment='.$this->quote('Gerenciado pelo RadiusHub').'} '
            .'else={/queue simple set $q target='.$this->quote($target)
            .' max-limit='.$this->quote($rateLimit).'}';
    }

    private function requiredIp(array $p, string $key): string
    {
        $value = $this->required($p, $key);
        if (filter_var($value, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException("Endereço IP inválido no parâmetro: {$key}");
        }

        return $value;
    }

    private function optionalIp(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        if (filter_var($value, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException('Endereço IP da sessão inválido.');
        }

        return $value;
    }

    private function requiredRateLimit(array $p, string $key): string
    {
        $value = $this->required($p, $key);
        if (! preg_match('/^\d+(?:[kKmMgG])?(?:\/\d+(?:[kKmMgG])?)?$/', $value)) {
            throw new InvalidArgumentException('Rate limit inválido. Use formatos como 10M ou 10M/50M.');
        }

        return $value;
    }

    private function sessionQueueName(mixed $identifier): string
    {
        $identifier = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $this->value($identifier)) ?: 'session';
        return mb_substr('RadiusHub-session-'.$identifier, 0, 120);
    }

    private function required(array $p, string $key): string
    {
        $value = $this->value($p[$key] ?? '');
        if ($value === '') throw new InvalidArgumentException("Parâmetro obrigatório ausente: {$key}");
        return $value;
    }

    private function value(mixed $value): string
    {
        $value = trim((string) $value);
        if (preg_match('/[\x00-\x1F\x7F]/u', $value)) {
            throw new InvalidArgumentException('O parâmetro contém caracteres de controle inválidos.');
        }
        return mb_substr($value, 0, 255);
    }

    private function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $this->value($value)).'"';
    }
}
