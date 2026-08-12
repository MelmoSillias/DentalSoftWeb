<?php

namespace App\Patient\Infrastructure\Persistence\Doctrine\Mapper;

use App\CareDelivery\Infrastructure\Persistence\Doctrine\Entity\Consultation;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
use App\Patient\Domain\Model\Allergy as DomainAllergy;
use App\Patient\Domain\Model\Antecedent as DomainAntecedent;
use App\Patient\Domain\Model\AssuranceProfile as DomainAssuranceProfile;
use App\Patient\Domain\Model\ContactUrgence as DomainContactUrgence;
use App\Patient\Domain\Model\Patient as DomainPatient;
use App\Patient\Domain\Model\SmsPreferences;
use App\Patient\Domain\ValueObject\PatientId;
use App\Patient\Infrastructure\Persistence\Doctrine\Entity\Allergy as EntityAllergy;
use App\Patient\Infrastructure\Persistence\Doctrine\Entity\Antecedent as EntityAntecedent;
use App\Patient\Infrastructure\Persistence\Doctrine\Entity\ContactUrgence as EntityContactUrgence;
use App\Patient\Infrastructure\Persistence\Doctrine\Entity\Patient as EntityPatient;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\PhoneNumber;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;

final class PatientMapper
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function toDomain(EntityPatient $entity): DomainPatient
    {
        $id = $entity->getId();
        if ($id === null) {
            throw new \LogicException('Cannot map Patient entity without an id.');
        }

        $allergies = [];
        foreach ($entity->getAllergies() as $allergy) {
            if (!$allergy instanceof EntityAllergy || $allergy->getId() === null) {
                continue;
            }
            $allergies[] = new DomainAllergy(
                $allergy->getId(),
                (string) $allergy->getLibelle(),
                $allergy->getDescription(),
            );
        }

        $antecedents = [];
        foreach ($entity->getAntecedents() as $antecedent) {
            if (!$antecedent instanceof EntityAntecedent || $antecedent->getId() === null) {
                continue;
            }
            $date = $antecedent->getDateEnregistrement();
            $antecedents[] = new DomainAntecedent(
                $antecedent->getId(),
                $antecedent->getDescription(),
                $antecedent->getType(),
                $date instanceof DateTimeInterface
                    ? DateTimeImmutable::createFromInterface($date)
                    : new DateTimeImmutable(),
            );
        }

        $contact = null;
        $entityContact = $entity->getContactUrgence();
        if ($entityContact instanceof EntityContactUrgence) {
            $contact = new DomainContactUrgence(
                $entityContact->getId(),
                $entityContact->getNom(),
                $entityContact->getTelephone(),
                $entityContact->getLienParente(),
            );
        }

        $assuranceProfile = null;
        $entityProfile = $entity->getAssuranceProfile();
        if ($entityProfile !== null) {
            $formData = $entityProfile->getFormData();
            $assuranceProfile = new DomainAssuranceProfile(
                $entityProfile->getId(),
                $entityProfile->getAssurance()?->getId(),
                isset($formData['numeroAssure']) ? (string) $formData['numeroAssure'] : (isset($formData['numero_assure']) ? (string) $formData['numero_assure'] : null),
                isset($formData['numeroAffiliation']) ? (string) $formData['numeroAffiliation'] : (isset($formData['numero_affiliation']) ? (string) $formData['numero_affiliation'] : null),
                $entityProfile->getCoverageRate(),
            );
        }

        $deletedAt = $entity->getDeletedAt();
        $dateInscription = $entity->getDateInscription() ?? new DateTimeImmutable();

        return DomainPatient::reconstitute(
            id: PatientId::fromInt($id),
            nom: (string) $entity->getNom(),
            prenom: (string) $entity->getPrenom(),
            sexe: (string) $entity->getSexe(),
            telephone: PhoneNumber::fromString((string) $entity->getTelephone()),
            email: Email::tryFromNullable($entity->getEmail()),
            adresse: $entity->getAdresse(),
            profession: $entity->getProfession(),
            lieuNaissance: $entity->getLieuNaissance(),
            dateNaissance: $entity->getDateNaissance(),
            dateInscription: $dateInscription,
            numCarnet: (string) $entity->getNumCarnet(),
            groupeSanguin: $entity->getGroupeSanguin(),
            referencement: (string) ($entity->getReferencement() ?? ''),
            smsPreferences: new SmsPreferences(
                patientCreated: $entity->isSmsPatientCreated(),
                receipt: $entity->isSmsReceipt(),
                ticket: $entity->isSmsTicket(),
                invoice: $entity->isSmsInvoice(),
                appointmentReminder: $entity->isSmsAppointmentReminder(),
                unsubscribed: $entity->isSmsUnsubscribed(),
                blacklisted: $entity->isSmsBlacklisted(),
            ),
            contactUrgence: $contact,
            assuranceProfile: $assuranceProfile,
            photo: $entity->getPhoto(),
            deletedAt: $deletedAt instanceof DateTimeInterface
                ? DateTimeImmutable::createFromInterface($deletedAt)
                : null,
            portalUserId: $entity->getPortalUser()?->getId(),
            lastConsultationId: $entity->getDerniereConsultation()?->getId(),
            allergies: $allergies,
            antecedents: $antecedents,
            archiveFiles: $entity->getArchiveFiles(),
        );
    }

    /**
     * Assigns DB-generated ids back onto domain allergies/antecedents after flush.
     */
    public function assignGeneratedChildIds(DomainPatient $domain, EntityPatient $entity): void
    {
        $claimedAllergyIds = [];
        foreach ($domain->getAllergies() as $domainAllergy) {
            if ($domainAllergy->getId() !== null) {
                $claimedAllergyIds[$domainAllergy->getId()] = true;
                continue;
            }

            foreach ($entity->getAllergies() as $entityAllergy) {
                if (!$entityAllergy instanceof EntityAllergy) {
                    continue;
                }
                $eid = $entityAllergy->getId();
                if ($eid === null || isset($claimedAllergyIds[$eid])) {
                    continue;
                }
                if (
                    (string) $entityAllergy->getLibelle() === $domainAllergy->getLibelle()
                    && $entityAllergy->getDescription() === $domainAllergy->getDescription()
                ) {
                    $domainAllergy->assignId($eid);
                    $claimedAllergyIds[$eid] = true;
                    break;
                }
            }
        }

        $claimedAntecedentIds = [];
        foreach ($domain->getAntecedents() as $domainAntecedent) {
            if ($domainAntecedent->getId() !== null) {
                $claimedAntecedentIds[$domainAntecedent->getId()] = true;
                continue;
            }

            foreach ($entity->getAntecedents() as $entityAntecedent) {
                if (!$entityAntecedent instanceof EntityAntecedent) {
                    continue;
                }
                $eid = $entityAntecedent->getId();
                if ($eid === null || isset($claimedAntecedentIds[$eid])) {
                    continue;
                }
                if (
                    $entityAntecedent->getDescription() === $domainAntecedent->getDescription()
                    && $entityAntecedent->getType() === $domainAntecedent->getType()
                ) {
                    $domainAntecedent->assignId($eid);
                    $claimedAntecedentIds[$eid] = true;
                    break;
                }
            }
        }
    }

    public function applyDomain(DomainPatient $domain, EntityPatient $entity): void
    {
        $entity->setNom($domain->getNom());
        $entity->setPrenom($domain->getPrenom());
        $entity->setSexe($domain->getSexe());
        $entity->setTelephone($domain->getTelephone()->toString());
        $entity->setEmail($domain->getEmail()?->toString());
        $entity->setAdresse($domain->getAdresse() ?? '');
        $entity->setProfession($domain->getProfession());
        $entity->setLieuNaissance($domain->getLieuNaissance());
        $entity->setDateNaissance($this->toMutableDate($domain->getDateNaissance()));
        $entity->setDateInscription($this->toMutableDateTime($domain->getDateInscription()) ?? new DateTime());
        $entity->setNumCarnet($domain->getNumCarnet());
        $entity->setGroupeSanguin($domain->getGroupeSanguin() ?? '');
        $entity->setReferencement($domain->getReferencement());
        $entity->setPhoto($domain->getPhoto());
        $entity->setArchiveFiles($domain->getArchiveFiles());
        $entity->setDeletedAt($this->toMutableDateTime($domain->getDeletedAt()));

        $sms = $domain->getSmsPreferences();
        $entity->setSmsPatientCreated($sms->isPatientCreated());
        $entity->setSmsReceipt($sms->isReceipt());
        $entity->setSmsTicket($sms->isTicket());
        $entity->setSmsInvoice($sms->isInvoice());
        $entity->setSmsAppointmentReminder($sms->isAppointmentReminder());
        $entity->setSmsUnsubscribed($sms->isUnsubscribed());
        $entity->setSmsBlacklisted($sms->isBlacklisted());

        $this->syncContactUrgence($domain, $entity);
        $this->syncAllergies($domain, $entity);
        $this->syncAntecedents($domain, $entity);
        $this->syncPortalUser($domain, $entity);
        $this->syncLastConsultation($domain, $entity);
    }

    private function syncContactUrgence(DomainPatient $domain, EntityPatient $entity): void
    {
        $domainContact = $domain->getContactUrgence();
        $entityContact = $entity->getContactUrgence();

        if ($domainContact === null || $domainContact->isEmpty()) {
            if ($entityContact !== null) {
                $entity->setContactUrgence(null);
                $this->em->remove($entityContact);
            }

            return;
        }

        if ($entityContact === null) {
            $entityContact = new EntityContactUrgence();
            $entityContact->setPatient($entity);
            $entity->setContactUrgence($entityContact);
        }

        $entityContact->setNom((string) ($domainContact->getNom() ?? ''));
        $entityContact->setTelephone((string) ($domainContact->getTelephone() ?? ''));
        $entityContact->setLienParente((string) ($domainContact->getLienParente() ?? ''));
        $this->em->persist($entityContact);
    }

    private function syncAllergies(DomainPatient $domain, EntityPatient $entity): void
    {
        /** @var array<int, EntityAllergy> $byId */
        $byId = [];
        foreach ($entity->getAllergies() as $allergy) {
            if ($allergy instanceof EntityAllergy && $allergy->getId() !== null) {
                $byId[$allergy->getId()] = $allergy;
            }
        }

        $keptIds = [];
        foreach ($domain->getAllergies() as $domainAllergy) {
            $allergyId = $domainAllergy->getId();
            if ($allergyId !== null && isset($byId[$allergyId])) {
                $entityAllergy = $byId[$allergyId];
                $entityAllergy->setLibelle($domainAllergy->getLibelle());
                $entityAllergy->setDescription($domainAllergy->getDescription());
                $keptIds[$allergyId] = true;
                continue;
            }

            $entityAllergy = new EntityAllergy();
            $entityAllergy->setLibelle($domainAllergy->getLibelle());
            $entityAllergy->setDescription($domainAllergy->getDescription());
            $entityAllergy->setPatient($entity);
            $entity->addAllergy($entityAllergy);
            $this->em->persist($entityAllergy);
        }

        foreach ($byId as $id => $entityAllergy) {
            if (!isset($keptIds[$id])) {
                $entity->removeAllergy($entityAllergy);
                $this->em->remove($entityAllergy);
            }
        }
    }

    private function syncAntecedents(DomainPatient $domain, EntityPatient $entity): void
    {
        /** @var array<int, EntityAntecedent> $byId */
        $byId = [];
        foreach ($entity->getAntecedents() as $antecedent) {
            if ($antecedent instanceof EntityAntecedent && $antecedent->getId() !== null) {
                $byId[$antecedent->getId()] = $antecedent;
            }
        }

        $keptIds = [];
        foreach ($domain->getAntecedents() as $domainAntecedent) {
            $antecedentId = $domainAntecedent->getId();
            if ($antecedentId !== null && isset($byId[$antecedentId])) {
                $entityAntecedent = $byId[$antecedentId];
                $entityAntecedent->setDescription($domainAntecedent->getDescription());
                $entityAntecedent->setType($domainAntecedent->getType());
                $entityAntecedent->setDateEnregistrement(
                    $this->toMutableDateTime($domainAntecedent->getDateEnregistrement()) ?? new DateTime()
                );
                $keptIds[$antecedentId] = true;
                continue;
            }

            $entityAntecedent = new EntityAntecedent();
            $entityAntecedent->setDescription($domainAntecedent->getDescription());
            $entityAntecedent->setType($domainAntecedent->getType());
            $entityAntecedent->setDateEnregistrement(
                $this->toMutableDateTime($domainAntecedent->getDateEnregistrement()) ?? new DateTime()
            );
            $entityAntecedent->setPatient($entity);
            $entity->addAntecedent($entityAntecedent);
            $this->em->persist($entityAntecedent);
        }

        foreach ($byId as $id => $entityAntecedent) {
            if (!isset($keptIds[$id])) {
                $entity->removeAntecedent($entityAntecedent);
            }
        }
    }

    private function syncPortalUser(DomainPatient $domain, EntityPatient $entity): void
    {
        $portalUserId = $domain->getPortalUserId();
        $currentId = $entity->getPortalUser()?->getId();

        if ($portalUserId === null) {
            if ($currentId !== null) {
                // Preserve existing portal link when domain snapshot has no portal id yet.
                return;
            }

            return;
        }

        if ($currentId === $portalUserId) {
            return;
        }

        $entity->setPortalUser($this->em->getReference(User::class, $portalUserId));
    }

    private function syncLastConsultation(DomainPatient $domain, EntityPatient $entity): void
    {
        $lastConsultationId = $domain->getLastConsultationId();
        if ($lastConsultationId === null) {
            $entity->setDerniereConsultation(null);

            return;
        }

        if ($entity->getDerniereConsultation()?->getId() === $lastConsultationId) {
            return;
        }

        $entity->setDerniereConsultation($this->em->getReference(Consultation::class, $lastConsultationId));
    }

    private function toMutableDate(?DateTimeInterface $value): ?DateTime
    {
        if ($value === null) {
            return null;
        }

        return DateTime::createFromInterface($value)->setTime(0, 0, 0);
    }

    private function toMutableDateTime(?DateTimeInterface $value): ?DateTime
    {
        if ($value === null) {
            return null;
        }

        return DateTime::createFromInterface($value);
    }
}
