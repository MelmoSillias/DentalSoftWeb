<?php

namespace App\Patient\Entity;

use App\Config\sexeEnum;
use App\CareDelivery\Entity\Consultation;
use App\CareDelivery\Entity\Traitement;
use App\ClinicalRecord\Entity\FicheMedicale;
use App\ClinicalRecord\Entity\FicheObservation;
use App\IdentityAccess\Entity\User;
use App\Patient\Repository\PatientRepository;
use App\Scheduling\Entity\Rdv;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PatientRepository::class)]
class Patient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $prenom = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateNaissance = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateInscription = null;

    #[ORM\Column(length: 10)]
    private ?string $sexe = null;

    #[ORM\Column(length: 55)]
    private ?string $telephone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $profession = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lieuNaissance = null;

    #[ORM\Column(length: 255)]
    private ?string $adresse = null;

    #[ORM\Column(length: 50)]
    private ?string $numCarnet = null;

    /**
     * @var Collection<int, Antecedent>
     */
    #[ORM\OneToMany(targetEntity: Antecedent::class, mappedBy: 'patient', orphanRemoval: true)]
    private Collection $antecedents;

    /**
     * @var Collection<int, Consultation>
     */
    #[ORM\OneToMany(targetEntity: Consultation::class, mappedBy: 'patient', orphanRemoval: true)]
    private Collection $consultations;

    /**
     * @var Collection<int, Rdv>
     */
    #[ORM\OneToMany(targetEntity: Rdv::class, mappedBy: 'patient')]
    private Collection $rdvs;

    /**
     * @var Collection<int, Traitement>
     */
    #[ORM\OneToMany(targetEntity: Traitement::class, mappedBy: 'patient', orphanRemoval: true)]
    private Collection $traitements;

    #[ORM\Column(type: 'string', length: 5)]
    private ?string $groupeSanguin = null;

    #[ORM\OneToMany(mappedBy: 'patient', targetEntity: Allergy::class)]
    private Collection $allergies;

    #[ORM\OneToOne(mappedBy: 'patient', targetEntity: ContactUrgence::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?ContactUrgence $contactUrgence = null;

    #[ORM\ManyToOne(targetEntity: Consultation::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Consultation $derniereConsultation = null;

    #[ORM\OneToMany(mappedBy: 'patient', targetEntity: FicheObservation::class)]
    private Collection $fichesObservation;

    #[ORM\OneToMany(mappedBy: 'patient', targetEntity: FicheMedicale::class)]
    private Collection $fichesMedicales;

    #[ORM\Column(length: 255)]
    private ?string $referencement = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $smsPatientCreated = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $smsReceipt = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $smsTicket = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $smsInvoice = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $smsAppointmentReminder = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $smsUnsubscribed = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $smsBlacklisted = false;

    #[ORM\OneToOne(inversedBy: 'portalPatient', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL', unique: true)]
    private ?User $portalUser = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\OneToOne(mappedBy: 'patient', targetEntity: PatientAssuranceProfile::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?PatientAssuranceProfile $assuranceProfile = null;

    public function __construct()
    {
        $this->antecedents = new ArrayCollection();
        $this->consultations = new ArrayCollection();
        $this->rdvs = new ArrayCollection();
        $this->traitements = new ArrayCollection();
        $this->allergies = new ArrayCollection();
        $this->fichesObservation = new ArrayCollection();
        $this->fichesMedicales = new ArrayCollection();
    }

    public function isSmsPatientCreated(): bool
    {
        return $this->smsPatientCreated;
    }

    public function setSmsPatientCreated(bool $smsPatientCreated): static
    {
        $this->smsPatientCreated = $smsPatientCreated;

        return $this;
    }

    public function isSmsReceipt(): bool
    {
        return $this->smsReceipt;
    }

    public function setSmsReceipt(bool $smsReceipt): static
    {
        $this->smsReceipt = $smsReceipt;

        return $this;
    }

    public function isSmsTicket(): bool
    {
        return $this->smsTicket;
    }

    public function setSmsTicket(bool $smsTicket): static
    {
        $this->smsTicket = $smsTicket;

        return $this;
    }

    public function isSmsInvoice(): bool
    {
        return $this->smsInvoice;
    }

    public function setSmsInvoice(bool $smsInvoice): static
    {
        $this->smsInvoice = $smsInvoice;

        return $this;
    }

    public function isSmsAppointmentReminder(): bool
    {
        return $this->smsAppointmentReminder;
    }

    public function setSmsAppointmentReminder(bool $smsAppointmentReminder): static
    {
        $this->smsAppointmentReminder = $smsAppointmentReminder;

        return $this;
    }

    public function isSmsUnsubscribed(): bool
    {
        return $this->smsUnsubscribed;
    }

    public function setSmsUnsubscribed(bool $smsUnsubscribed): static
    {
        $this->smsUnsubscribed = $smsUnsubscribed;

        return $this;
    }

    public function isSmsBlacklisted(): bool
    {
        return $this->smsBlacklisted;
    }

    public function setSmsBlacklisted(bool $smsBlacklisted): static
    {
        $this->smsBlacklisted = $smsBlacklisted;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAssuranceProfile(): ?PatientAssuranceProfile
    {
        return $this->assuranceProfile;
    }

    public function setAssuranceProfile(?PatientAssuranceProfile $assuranceProfile): static
    {
        if ($assuranceProfile === null && $this->assuranceProfile !== null) {
            $this->assuranceProfile->setPatient(null);
        }

        if ($assuranceProfile !== null && $assuranceProfile->getPatient() !== $this) {
            $assuranceProfile->setPatient($this);
        }

        $this->assuranceProfile = $assuranceProfile;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getFullName(): ?string
    {
        return $this->nom . ' ' . $this->prenom;
    }

    public function getDateNaissance(): ?\DateTimeInterface
    {
        return $this->dateNaissance;
    }

    public function setDateNaissance(?\DateTimeInterface $dateNaissance): static
    {
        $this->dateNaissance = $dateNaissance;

        return $this;
    }

    public function getAge(): ?int
    {
        if (!$this->dateNaissance) {
            return null;
        }

        $today = new \DateTime();

        return $today->diff($this->dateNaissance)->y;
    }

    public function getDateInscription(): ?\DateTimeInterface
    {
        return $this->dateInscription;
    }

    public function setDateInscription(\DateTimeInterface $dateInscription): static
    {
        $this->dateInscription = $dateInscription;

        return $this;
    }

    public function getSexe(): ?string
    {
        return $this->sexe;
    }

    public function setSexe(string $sexe): static
    {
        $this->sexe = $sexe;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getProfession(): ?string
    {
        return $this->profession;
    }

    public function setProfession(?string $profession): static
    {
        $this->profession = $profession;

        return $this;
    }

    public function getLieuNaissance(): ?string
    {
        return $this->lieuNaissance;
    }

    public function setLieuNaissance(?string $lieuNaissance): static
    {
        $this->lieuNaissance = $lieuNaissance;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getNumCarnet(): ?string
    {
        return $this->numCarnet;
    }

    public function setNumCarnet(string $numCarnet): static
    {
        $this->numCarnet = $numCarnet;

        return $this;
    }

    /**
     * @return Collection<int, Antecedent>
     */
    public function getAntecedents(): Collection
    {
        return $this->antecedents;
    }

    public function addAntecedent(Antecedent $antecedent): static
    {
        if (!$this->antecedents->contains($antecedent)) {
            $this->antecedents->add($antecedent);
            $antecedent->setPatient($this);
        }

        return $this;
    }

    public function removeAntecedent(Antecedent $antecedent): static
    {
        if ($this->antecedents->removeElement($antecedent) && $antecedent->getPatient() === $this) {
            $antecedent->setPatient(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, Consultation>
     */
    public function getConsultations(): Collection
    {
        return $this->consultations;
    }

    public function addConsultation(Consultation $consultation): static
    {
        if (!$this->consultations->contains($consultation)) {
            $this->consultations->add($consultation);
            $consultation->setPatient($this);
        }

        return $this;
    }

    public function removeConsultation(Consultation $consultation): static
    {
        if ($this->consultations->removeElement($consultation) && $consultation->getPatient() === $this) {
            $consultation->setPatient(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, Rdv>
     */
    public function getRdvs(): Collection
    {
        return $this->rdvs;
    }

    public function addRdv(Rdv $rdv): static
    {
        if (!$this->rdvs->contains($rdv)) {
            $this->rdvs->add($rdv);
            $rdv->setPatient($this);
        }

        return $this;
    }

    public function removeRdv(Rdv $rdv): static
    {
        if ($this->rdvs->removeElement($rdv) && $rdv->getPatient() === $this) {
            $rdv->setPatient(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, Traitement>
     */
    public function getTraitements(): Collection
    {
        return $this->traitements;
    }

    public function addTraitement(Traitement $traitement): static
    {
        if (!$this->traitements->contains($traitement)) {
            $this->traitements->add($traitement);
            $traitement->setPatient($this);
        }

        return $this;
    }

    public function removeTraitement(Traitement $traitement): static
    {
        if ($this->traitements->removeElement($traitement) && $traitement->getPatient() === $this) {
            $traitement->setPatient(null);
        }

        return $this;
    }

    public function getGroupeSanguin(): ?string
    {
        return $this->groupeSanguin;
    }

    public function setGroupeSanguin(string $groupeSanguin): static
    {
        $this->groupeSanguin = $groupeSanguin;

        return $this;
    }

    /**
     * @return Collection<int, Allergy>
     */
    public function getAllergies(): Collection
    {
        return $this->allergies;
    }

    public function addAllergy(Allergy $allergy): static
    {
        if (!$this->allergies->contains($allergy)) {
            $this->allergies->add($allergy);
            $allergy->setPatient($this);
        }

        return $this;
    }

    public function removeAllergy(Allergy $allergy): static
    {
        if ($this->allergies->removeElement($allergy) && $allergy->getPatient() === $this) {
            $allergy->setPatient(null);
        }

        return $this;
    }

    public function getContactUrgence(): ?ContactUrgence
    {
        return $this->contactUrgence;
    }

    public function setContactUrgence(?ContactUrgence $contactUrgence): static
    {
        $this->contactUrgence = $contactUrgence;

        if ($contactUrgence && $contactUrgence->getPatient() !== $this) {
            $contactUrgence->setPatient($this);
        }

        return $this;
    }

    public function getDerniereConsultation(): ?Consultation
    {
        return $this->derniereConsultation;
    }

    public function setDerniereConsultation(?Consultation $derniereConsultation): static
    {
        $this->derniereConsultation = $derniereConsultation;

        return $this;
    }

    /**
     * @return Collection<int, FicheObservation>
     */
    public function getFichesObservation(): Collection
    {
        return $this->fichesObservation;
    }

    public function addFichesObservation(FicheObservation $fichesObservation): static
    {
        if (!$this->fichesObservation->contains($fichesObservation)) {
            $this->fichesObservation->add($fichesObservation);
            $fichesObservation->setPatient($this);
        }

        return $this;
    }

    public function removeFichesObservation(FicheObservation $fichesObservation): static
    {
        if ($this->fichesObservation->removeElement($fichesObservation) && $fichesObservation->getPatient() === $this) {
            $fichesObservation->setPatient(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, FicheMedicale>
     */
    public function getFichesMedicales(): Collection
    {
        return $this->fichesMedicales;
    }

    public function addFichesMedicale(FicheMedicale $fichesMedicale): static
    {
        if (!$this->fichesMedicales->contains($fichesMedicale)) {
            $this->fichesMedicales->add($fichesMedicale);
            $fichesMedicale->setPatient($this);
        }

        return $this;
    }

    public function removeFichesMedicale(FicheMedicale $fichesMedicale): static
    {
        if ($this->fichesMedicales->removeElement($fichesMedicale) && $fichesMedicale->getPatient() === $this) {
            $fichesMedicale->setPatient(null);
        }

        return $this;
    }

    public function getReferencement(): ?string
    {
        return $this->referencement;
    }

    public function setReferencement(string $referencement): static
    {
        $this->referencement = $referencement;

        return $this;
    }

    public function getPortalUser(): ?User
    {
        return $this->portalUser;
    }

    public function setPortalUser(?User $portalUser): static
    {
        if ($portalUser === null && $this->portalUser !== null) {
            $old = $this->portalUser;
            $this->portalUser = null;
            $old->setPortalPatient(null);
        } else {
            $this->portalUser = $portalUser;
        }

        if ($portalUser !== null && $portalUser->getPortalPatient() !== $this) {
            $portalUser->setPortalPatient($this);
        }

        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;

        return $this;
    }
}