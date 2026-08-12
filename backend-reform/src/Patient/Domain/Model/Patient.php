<?php

namespace App\Patient\Domain\Model;

use App\Patient\Domain\Exception\PatientAlreadyDeletedException;
use App\Patient\Domain\Exception\PatientDomainException;
use App\Patient\Domain\ValueObject\PatientId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\PhoneNumber;
use DateTimeImmutable;
use DateTimeInterface;

final class Patient
{
    /** @var list<Allergy> */
    private array $allergies = [];

    /** @var list<Antecedent> */
    private array $antecedents = [];

    /** @var list<array{nom?: string, url: string}|string> */
    private array $archiveFiles = [];

    private function __construct(
        private ?PatientId $id,
        private string $nom,
        private string $prenom,
        private string $sexe,
        private PhoneNumber $telephone,
        private ?Email $email,
        private ?string $adresse,
        private ?string $profession,
        private ?string $lieuNaissance,
        private ?DateTimeInterface $dateNaissance,
        private DateTimeInterface $dateInscription,
        private string $numCarnet,
        private ?string $groupeSanguin,
        private string $referencement,
        private SmsPreferences $smsPreferences,
        private ?ContactUrgence $contactUrgence,
        private ?AssuranceProfile $assuranceProfile,
        private ?string $photo,
        private ?DateTimeImmutable $deletedAt,
        private ?int $portalUserId,
        private ?int $lastConsultationId,
    ) {
        $this->assertIdentity();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data, DateTimeImmutable $now): self
    {
        $nom = trim((string) ($data['nom'] ?? ''));
        $prenom = trim((string) ($data['prenom'] ?? ''));
        $sexe = trim((string) ($data['sexe'] ?? ''));
        $telephoneRaw = trim((string) ($data['telephone'] ?? ''));

        if ($nom === '' || $prenom === '' || $sexe === '' || $telephoneRaw === '') {
            throw new PatientDomainException('Paramètres obligatoires manquants');
        }

        $contact = null;
        if (isset($data['contactUrgence']) && is_array($data['contactUrgence'])) {
            $c = ContactUrgence::create(
                $data['contactUrgence']['nom'] ?? null,
                $data['contactUrgence']['telephone'] ?? null,
                $data['contactUrgence']['lienParente'] ?? null,
            );
            $contact = $c->isEmpty() ? null : $c;
        }

        return new self(
            id: null,
            nom: $nom,
            prenom: $prenom,
            sexe: $sexe,
            telephone: PhoneNumber::fromString($telephoneRaw),
            email: Email::tryFromNullable(isset($data['email']) ? (string) $data['email'] : null),
            adresse: isset($data['adresse']) ? (string) $data['adresse'] : null,
            profession: isset($data['profession']) ? (string) $data['profession'] : null,
            lieuNaissance: isset($data['lieuNaissance']) ? (string) $data['lieuNaissance'] : null,
            dateNaissance: !empty($data['dateNaissance']) ? new DateTimeImmutable((string) $data['dateNaissance']) : null,
            dateInscription: $now,
            numCarnet: uniqid('PAT-', true),
            groupeSanguin: isset($data['groupeSanguin']) ? (string) $data['groupeSanguin'] : null,
            referencement: (string) ($data['referencement'] ?? ''),
            smsPreferences: SmsPreferences::fromArray(
                isset($data['smsPreferences']) && is_array($data['smsPreferences']) ? $data['smsPreferences'] : $data
            ),
            contactUrgence: $contact,
            assuranceProfile: null,
            photo: null,
            deletedAt: null,
            portalUserId: null,
            lastConsultationId: null,
        );
    }

    /**
     * @param list<Allergy> $allergies
     * @param list<Antecedent> $antecedents
     * @param list<array{nom?: string, url: string}|string> $archiveFiles
     */
    public static function reconstitute(
        PatientId $id,
        string $nom,
        string $prenom,
        string $sexe,
        PhoneNumber $telephone,
        ?Email $email,
        ?string $adresse,
        ?string $profession,
        ?string $lieuNaissance,
        ?DateTimeInterface $dateNaissance,
        DateTimeInterface $dateInscription,
        string $numCarnet,
        ?string $groupeSanguin,
        string $referencement,
        SmsPreferences $smsPreferences,
        ?ContactUrgence $contactUrgence,
        ?AssuranceProfile $assuranceProfile,
        ?string $photo,
        ?DateTimeImmutable $deletedAt,
        ?int $portalUserId,
        ?int $lastConsultationId,
        array $allergies = [],
        array $antecedents = [],
        array $archiveFiles = [],
    ): self {
        $patient = new self(
            $id,
            $nom,
            $prenom,
            $sexe,
            $telephone,
            $email,
            $adresse,
            $profession,
            $lieuNaissance,
            $dateNaissance,
            $dateInscription,
            $numCarnet,
            $groupeSanguin,
            $referencement,
            $smsPreferences,
            $contactUrgence,
            $assuranceProfile,
            $photo,
            $deletedAt,
            $portalUserId,
            $lastConsultationId,
        );
        $patient->allergies = $allergies;
        $patient->antecedents = $antecedents;
        $patient->archiveFiles = $archiveFiles;

        return $patient;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(array $data): void
    {
        $this->assertActive();

        if (array_key_exists('nom', $data) && $data['nom'] !== null) {
            $this->nom = trim((string) $data['nom']);
        }
        if (array_key_exists('prenom', $data) && $data['prenom'] !== null) {
            $this->prenom = trim((string) $data['prenom']);
        }
        if (array_key_exists('sexe', $data) && $data['sexe'] !== null) {
            $this->sexe = trim((string) $data['sexe']);
        }
        if (array_key_exists('telephone', $data) && $data['telephone'] !== null) {
            $this->telephone = PhoneNumber::fromString((string) $data['telephone']);
        }
        if (array_key_exists('email', $data)) {
            $this->email = Email::tryFromNullable($data['email'] !== null ? (string) $data['email'] : null);
        }
        if (array_key_exists('adresse', $data)) {
            $this->adresse = $data['adresse'] !== null ? (string) $data['adresse'] : null;
        }
        if (array_key_exists('profession', $data)) {
            $this->profession = $data['profession'] !== null ? (string) $data['profession'] : null;
        }
        if (array_key_exists('lieuNaissance', $data)) {
            $this->lieuNaissance = $data['lieuNaissance'] !== null ? (string) $data['lieuNaissance'] : null;
        }
        if (array_key_exists('groupeSanguin', $data)) {
            $this->groupeSanguin = $data['groupeSanguin'] !== null ? (string) $data['groupeSanguin'] : null;
        }
        if (array_key_exists('referencement', $data)) {
            $this->referencement = (string) ($data['referencement'] ?? '');
        }
        if (!empty($data['dateNaissance'])) {
            $this->dateNaissance = new DateTimeImmutable((string) $data['dateNaissance']);
        }

        $smsSource = isset($data['smsPreferences']) && is_array($data['smsPreferences'])
            ? $data['smsPreferences']
            : $data;
        $this->smsPreferences = SmsPreferences::fromArray($smsSource, $this->smsPreferences);

        if (isset($data['contactUrgence']) && is_array($data['contactUrgence'])) {
            $urgence = $data['contactUrgence'];
            $hasContact = !empty($urgence['nom']) || !empty($urgence['telephone']) || !empty($urgence['lienParente']);
            if ($hasContact) {
                $existingId = $this->contactUrgence?->getId();
                $contact = ContactUrgence::create(
                    $urgence['nom'] ?? null,
                    $urgence['telephone'] ?? null,
                    $urgence['lienParente'] ?? null,
                );
                $this->contactUrgence = $existingId !== null ? $contact->withId($existingId) : $contact;
            } else {
                $this->contactUrgence = null;
            }
        }

        $this->assertIdentity();
    }

    public function softDelete(DateTimeImmutable $at): void
    {
        $this->assertActive();
        $this->deletedAt = $at;
        $this->lastConsultationId = null;
    }

    public function restore(): void
    {
        if ($this->deletedAt === null) {
            throw new PatientDomainException('Patient is not deleted.');
        }
        $this->deletedAt = null;
    }

    public function addAllergy(Allergy $allergy): void
    {
        $this->assertActive();
        $this->allergies[] = $allergy;
    }

    public function removeAllergy(int $allergyId): void
    {
        $this->assertActive();
        $before = count($this->allergies);
        $this->allergies = array_values(array_filter(
            $this->allergies,
            static fn (Allergy $a): bool => $a->getId() !== $allergyId
        ));
        if (count($this->allergies) === $before) {
            throw new PatientDomainException('Allergy not found on patient.');
        }
    }

    public function addAntecedent(Antecedent $antecedent): void
    {
        $this->assertActive();
        $this->antecedents[] = $antecedent;
    }

    public function removeAntecedent(int $antecedentId): void
    {
        $this->assertActive();
        $before = count($this->antecedents);
        $this->antecedents = array_values(array_filter(
            $this->antecedents,
            static fn (Antecedent $a): bool => $a->getId() !== $antecedentId
        ));
        if (count($this->antecedents) === $before) {
            throw new PatientDomainException('Antecedent not found on patient.');
        }
    }

    public function assignId(PatientId $id): void
    {
        if ($this->id !== null) {
            throw new PatientDomainException('Patient already has an id.');
        }
        $this->id = $id;
    }

    public function setPhoto(?string $photo): void
    {
        $this->assertActive();
        $this->photo = $photo;
    }

    /**
     * @param list<array{nom?: string, url: string}|string> $files
     */
    public function setArchiveFiles(array $files): void
    {
        $this->assertActive();
        $this->archiveFiles = $files;
    }

    public function setAssuranceProfile(?AssuranceProfile $profile): void
    {
        $this->assertActive();
        $this->assuranceProfile = $profile;
    }

    public function setPortalUserId(?int $userId): void
    {
        $this->portalUserId = $userId;
    }

    public function clearLastConsultation(): void
    {
        $this->lastConsultationId = null;
    }

    public function getId(): ?PatientId
    {
        return $this->id;
    }

    public function requireId(): PatientId
    {
        if ($this->id === null) {
            throw new PatientDomainException('Patient id is not assigned.');
        }

        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function getPrenom(): string
    {
        return $this->prenom;
    }

    public function getFullName(): string
    {
        return trim($this->prenom . ' ' . $this->nom);
    }

    public function getSexe(): string
    {
        return $this->sexe;
    }

    public function getTelephone(): PhoneNumber
    {
        return $this->telephone;
    }

    public function getEmail(): ?Email
    {
        return $this->email;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function getProfession(): ?string
    {
        return $this->profession;
    }

    public function getLieuNaissance(): ?string
    {
        return $this->lieuNaissance;
    }

    public function getDateNaissance(): ?DateTimeInterface
    {
        return $this->dateNaissance;
    }

    public function getDateInscription(): DateTimeInterface
    {
        return $this->dateInscription;
    }

    public function getNumCarnet(): string
    {
        return $this->numCarnet;
    }

    public function getGroupeSanguin(): ?string
    {
        return $this->groupeSanguin;
    }

    public function getReferencement(): string
    {
        return $this->referencement;
    }

    public function getSmsPreferences(): SmsPreferences
    {
        return $this->smsPreferences;
    }

    public function getContactUrgence(): ?ContactUrgence
    {
        return $this->contactUrgence;
    }

    public function getAssuranceProfile(): ?AssuranceProfile
    {
        return $this->assuranceProfile;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function getPortalUserId(): ?int
    {
        return $this->portalUserId;
    }

    public function getLastConsultationId(): ?int
    {
        return $this->lastConsultationId;
    }

    /** @return list<Allergy> */
    public function getAllergies(): array
    {
        return $this->allergies;
    }

    /** @return list<Antecedent> */
    public function getAntecedents(): array
    {
        return $this->antecedents;
    }

    /** @return list<array{nom?: string, url: string}|string> */
    public function getArchiveFiles(): array
    {
        return $this->archiveFiles;
    }

    private function assertIdentity(): void
    {
        if ($this->nom === '' || $this->prenom === '' || $this->sexe === '') {
            throw new PatientDomainException('Patient identity is incomplete.');
        }
    }

    private function assertActive(): void
    {
        if ($this->isDeleted()) {
            $id = $this->id?->toInt() ?? 0;
            throw PatientAlreadyDeletedException::withId($id);
        }
    }
}
