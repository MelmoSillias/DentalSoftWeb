<?php

namespace App\Communication\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\IpUtils;

final class MailboxHostPolicy
{
    public function __construct(#[Autowire('%env(csv:VITRINE_IMAP_ALLOWED_HOSTS)%')] private array $allowedHosts) {}

    public function assertAllowedName(string $host): void
    {
        $allowed = array_map(fn($value) => strtolower(trim($value)), $this->allowedHosts);
        if (!filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || !str_contains($host, '.')
            || filter_var($host, FILTER_VALIDATE_IP) || !in_array(strtolower($host), $allowed, true)) {
            throw new \InvalidArgumentException('Serveur IMAP non autorisé. Un administrateur doit configurer VITRINE_IMAP_ALLOWED_HOSTS sur le serveur.');
        }
    }

    public static function assertPublicAddresses(array $addresses): void
    {
        if ($addresses === []) { throw new \InvalidArgumentException('Le serveur IMAP ne possède aucune adresse publique.'); }
        foreach ($addresses as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
                || IpUtils::checkIp($ip, ['100.64.0.0/10', '::ffff:0:0/96', '64:ff9b::/96'])) {
                throw new \InvalidArgumentException('Les adresses IMAP privées ou réservées sont interdites.');
            }
        }
    }

    public function assertConnectable(string $host): void
    {
        $this->assertAllowedName($host);
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        $addresses = [];
        foreach ($records ?: [] as $record) {
            if (isset($record['ip'])) $addresses[] = $record['ip'];
            if (isset($record['ipv6'])) $addresses[] = $record['ipv6'];
        }
        self::assertPublicAddresses($addresses);
    }
}
