<?php

declare(strict_types=1);

namespace App\Focus\Application\Port;

use App\Billing\Domain\Entity\Facture;
use App\Billing\Domain\Entity\Paiement;
use App\Billing\Domain\Entity\Transaction;
use App\CareDelivery\Domain\Entity\Consultation;
use App\IdentityAccess\Domain\Entity\Employe;
use App\Scheduling\Domain\Entity\Rdv;

/** Read-only projections across contexts; no query builder crosses this port. */
interface DashboardQueriesInterface
{
    public function countNewPatients(\DateTimeImmutable $from, \DateTimeImmutable $to, ?Employe $doctor = null): int;
    public function countPatients(?Employe $doctor = null): int;
    public function countConsultations(\DateTimeImmutable $from, \DateTimeImmutable $to, ?Employe $doctor = null): int;
    public function countPaidConsultations(\DateTimeImmutable $from, \DateTimeImmutable $to, ?Employe $doctor = null): int;
    public function countAppointments(\DateTimeImmutable $from, \DateTimeImmutable $to, int $status, ?Employe $doctor = null): int;
    /** @return list<Consultation> */
    public function pendingConsultations(\DateTimeImmutable $from, \DateTimeImmutable $to, ?Employe $doctor = null, ?int $limit = null): array;
    /** @return list<Consultation> */
    public function doctorConsultations(Employe $doctor, \DateTimeImmutable $from, \DateTimeImmutable $to): array;
    /** @return list<Facture> */
    public function invoicesForPeriod(\DateTimeImmutable $from, \DateTimeImmutable $to): array;
    /** @return list<Facture> */
    public function invoicesForDoctor(Employe $doctor, \DateTimeImmutable $from, \DateTimeImmutable $to): array;
    /** @return list<Facture> */
    public function recentInvoices(\DateTimeImmutable $from, \DateTimeImmutable $to, ?Employe $doctor = null): array;
    /** @return list<Rdv> */
    public function pendingAppointments(\DateTimeImmutable $from, \DateTimeImmutable $to, ?Employe $doctor = null): array;
    /** @return list<Paiement> */
    public function recentPayments(\DateTimeImmutable $from, \DateTimeImmutable $to): array;
    /** @return list<Paiement> */
    public function doctorPayments(Employe $doctor, \DateTimeImmutable $from, \DateTimeImmutable $to): array;
    /** @return list<Transaction> */
    public function transactions(\DateTimeImmutable $from, \DateTimeImmutable $to, ?string $type = null): array;
    /** @return list<array{id:mixed,name:string,total:mixed,paid:mixed}> */
    public function doctorConsultationTotals(\DateTimeImmutable $from, \DateTimeImmutable $to): array;
    /** @return list<array{id:mixed,name:string,acts:mixed}> */
    public function doctorActTotals(\DateTimeImmutable $from, \DateTimeImmutable $to): array;
    /** @return list<array{label:string,total:mixed}> */
    public function topDoctorActs(Employe $doctor, \DateTimeImmutable $from, \DateTimeImmutable $to): array;
}
