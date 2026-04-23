<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CryptoService
{
    public function __construct(
        #[Autowire('%kernel.secret%')]
        private readonly string $appSecret,
    ) {
    }

    public function encrypt(string $plain): string
    {
        if ($plain === '') {
            return '';
        }

        $iv = random_bytes(16);
        $key = hash('sha256', $this->appSecret, true);
        $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if ($cipher === false) {
            throw new \RuntimeException('Échec du chiffrement des paramètres SMS.');
        }

        return base64_encode($iv . $cipher);
    }

    public function decrypt(?string $ciphertext): ?string
    {
        if ($ciphertext === null || $ciphertext === '') {
            return null;
        }

        $raw = base64_decode($ciphertext, true);
        if ($raw === false || strlen($raw) < 17) {
            return null;
        }

        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $key = hash('sha256', $this->appSecret, true);
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return $plain === false ? null : $plain;
    }
}
