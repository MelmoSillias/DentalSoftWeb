<?php

namespace App\Patient\Domain\Model;

final class SmsPreferences
{
    public function __construct(
        private bool $patientCreated = false,
        private bool $receipt = false,
        private bool $ticket = false,
        private bool $invoice = false,
        private bool $appointmentReminder = false,
        private bool $unsubscribed = false,
        private bool $blacklisted = false,
    ) {
    }

    public static function defaults(): self
    {
        return new self();
    }

    public function withPatientCreated(bool $value): self
    {
        $clone = clone $this;
        $clone->patientCreated = $value;

        return $clone;
    }

    public function isPatientCreated(): bool
    {
        return $this->patientCreated;
    }

    public function isReceipt(): bool
    {
        return $this->receipt;
    }

    public function isTicket(): bool
    {
        return $this->ticket;
    }

    public function isInvoice(): bool
    {
        return $this->invoice;
    }

    public function isAppointmentReminder(): bool
    {
        return $this->appointmentReminder;
    }

    public function isUnsubscribed(): bool
    {
        return $this->unsubscribed;
    }

    public function isBlacklisted(): bool
    {
        return $this->blacklisted;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, ?self $current = null): self
    {
        $base = $current ?? self::defaults();

        return new self(
            patientCreated: array_key_exists('smsPatientCreated', $data) ? (bool) $data['smsPatientCreated'] : $base->patientCreated,
            receipt: array_key_exists('smsReceipt', $data) ? (bool) $data['smsReceipt'] : $base->receipt,
            ticket: array_key_exists('smsTicket', $data) ? (bool) $data['smsTicket'] : $base->ticket,
            invoice: array_key_exists('smsInvoice', $data) ? (bool) $data['smsInvoice'] : $base->invoice,
            appointmentReminder: array_key_exists('smsAppointmentReminder', $data) ? (bool) $data['smsAppointmentReminder'] : $base->appointmentReminder,
            unsubscribed: array_key_exists('smsUnsubscribed', $data) ? (bool) $data['smsUnsubscribed'] : $base->unsubscribed,
            blacklisted: array_key_exists('smsBlacklisted', $data) ? (bool) $data['smsBlacklisted'] : $base->blacklisted,
        );
    }
}
