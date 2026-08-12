<?php

namespace App\Billing\Application\Port;

interface FinanceWritePort
{
    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function createPaymentMethod(array $data): array;

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function updatePaymentMethod(int $id, array $data): array;

    /**
     * @return array<string, mixed>
     */
    public function deletePaymentMethod(int $id): array;

    /**
     * @return array<string, mixed>
     */
    public function togglePaymentMethod(int $id): array;

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function createFixedCharge(array $data): array;

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function updateFixedCharge(int $id, array $data): array;

    /**
     * @return array<string, mixed>
     */
    public function deleteFixedCharge(int $id): array;
}
