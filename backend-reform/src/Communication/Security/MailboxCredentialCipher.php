<?php

namespace App\Communication\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class MailboxCredentialCipher
{
    public function __construct(#[Autowire('%kernel.secret%'), \SensitiveParameter] private string $secret) {}

    public function encrypt(#[\SensitiveParameter] string $plain, string $context): string
    {
        $nonce = random_bytes(12);
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $nonce, $tag, $context);
        if ($cipher === false) throw new \RuntimeException('Impossible de chiffrer les identifiants mail.');
        return 'gcm1:'.base64_encode($nonce.$tag.$cipher);
    }

    public function decrypt(string $value, string $context): string
    {
        $raw = str_starts_with($value, 'gcm1:') ? base64_decode(substr($value, 5), true) : false;
        if ($raw === false || strlen($raw) < 29) throw new \RuntimeException('Veuillez reconnecter l’email de contact avec son mot de passe.');
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), $context);
        if ($plain === false) throw new \RuntimeException('Identifiants mail invalides : reconnectez la boîte de contact.');
        return $plain;
    }

    private function key(): string { return hash_hkdf('sha256', $this->secret, 32, 'vitrine-mailbox-credentials'); }
}
