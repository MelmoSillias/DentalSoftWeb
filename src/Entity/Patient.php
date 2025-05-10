<?php

namespace App\Entity;
use App\Config\sexeEnum;

use App\Repository\PatientRepository; 
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

    #[ORM\OneToMany(mappedBy: 'patient', targetEntity: ContactUrgence::class)]
    private Collection $contactsUrgence;

    #[ORM\ManyToOne(targetEntity: Consultation::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Consultation $derniereConsultation = null;

    #[ORM\OneToMany(mappedBy: 'patient', targetEntity: FicheObservation::class)]
    private Collection $fichesObservation;

    public function __construct()
    {
        $this->antecedents = new ArrayCollection();
        $this->consultations = new ArrayCollection();
        $this->rdvs = new ArrayCollection();
        $this->traitements = new ArrayCollection();
        $this->allergies         = new ArrayCollection(); 
        $this->contactsUrgence   = new ArrayCollection();
        $this->fichesObservation = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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
        $age = $today->diff($this->dateNaissance)->y;

        return $age;
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
        if ($this->antecedents->removeElement($antecedent)) {
            // set the owning side to null (unless already changed)
            if ($antecedent->getPatient() === $this) {
                $antecedent->setPatient(null);
            }
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
        if ($this->consultations->removeElement($consultation)) {
            // set the owning side to null (unless already changed)
            if ($consultation->getPatient() === $this) {
                $consultation->setPatient(null);
            }
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
        if ($this->rdvs->removeElement($rdv)) {
            // set the owning side to null (unless already changed)
            if ($rdv->getPatient() === $this) {
                $rdv->setPatient(null);
            }
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
        if ($this->traitements->removeElement($traitement)) {
            // set the owning side to null (unless already changed)
            if ($traitement->getPatient() === $this) {
                $traitement->setPatient(null);
            }
        }

        return $this;
    }

   /**
     * @return Collection<int, Allergy>
     */
    public function getAllergies(): Collection
    {
        return $this->allergies;
    }
    public function addAllergy(Allergy $a): self
    {
        if (!$this->allergies->contains($a)) {
            $this->allergies->add($a);
            $a->setPatient($this);
        }
        return $this;
    }
    public function removeAllergy(Allergy $a): self
    {
        if ($this->allergies->removeElement($a)) {
            if ($a->getPatient() === $this) {
                $a->setPatient(null);
            }
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
     * @return Collection<int, ContactUrgence>
     */
    public function getContactsUrgence(): Collection
    {
        return $this->contactsUrgence;
    }
    public function addContactUrgence(ContactUrgence $c): self
    {
        if (!$this->contactsUrgence->contains($c)) {
            $this->contactsUrgence->add($c);
            $c->setPatient($this);
        }
        return $this;
    }
    public function removeContactUrgence(ContactUrgence $c): self
    {
        if ($this->contactsUrgence->removeElement($c)) {
            if ($c->getPatient() === $this) {
                $c->setPatient(null);
            }
        }
        return $this;
    }

    public function getDerniereConsultation(): ?Consultation
    {
        return $this->derniereConsultation;
    }
    public function setDerniereConsultation(?Consultation $c): self
    {
        $this->derniereConsultation = $c;
        return $this;
    }

    public function getFichesObservation(): Collection
    {
        return $this->fichesObservation;
    }
    public function addFicheObservation(FicheObservation $fiche): self
    {
        if (!$this->fichesObservation->contains($fiche)) {
            $this->fichesObservation[] = $fiche;
            $fiche->setPatient($this);
        }

        return $this;
    }
    public function removeFicheObservation(FicheObservation $fiche): self
    {
        if ($this->fichesObservation->removeElement($fiche)) {
            // set the owning side to null (unless already changed)
            if ($fiche->getPatient() === $this) {
                $fiche->setPatient(null);
            }
        }

        return $this;
    }

}
