<?php

namespace App\CareDelivery\Application\Port;

interface ConsultationNotificationPort
{
    public function notifyCreation(object $consultation, ?object $triggeredBy): void;

    public function notifyReceptionOnClosure(object $consultation, float $invoiceAmount): void;

    public function notifyCancelled(object $consultation, ?object $actor): void;
}
