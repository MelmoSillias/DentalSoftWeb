<?php

namespace App\Entity;

use App\Repository\EmployeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmployeRepository::class)]
class Employe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $prenom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 255)]
    private ?string $fonction = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateEmbauche = null;

    #[ORM\Column(type: Types::JSON)]
    private array $comingDaysInWeek = [];

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isOnDaysOff = false;

    #[ORM\Column(length: 255)]
    private ?string $typeSalaire = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $valeurSalaire;


    #[ORM\Column(length: 255, unique: true, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 100, unique: true, nullable: true)]
    private ?string $matricule = null;

    #[ORM\Column(length: 100)]
    private ?string $typeContrat = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $dureeContrat = null;

    #[ORM\Column(type: Types::JSON)]
    private array $administrativeFiles = [];

    #[ORM\OneToMany(mappedBy: 'medecin', targetEntity: Consultation::class)]
    private Collection $consultationsAsMedecin;

    #[ORM\OneToMany(mappedBy: 'infirmier', targetEntity: Consultation::class)]
    private Collection $consultationsAsInfirmier;

    #[ORM\OneToMany(mappedBy: 'employee', targetEntity: Stock::class)]
    private Collection $stocks;

    #[ORM\OneToMany(mappedBy: 'medecin', targetEntity: Rdv::class)]
    private Collection $rdvs;

    #[ORM\OneToMany(mappedBy: 'medecin', targetEntity: Traitement::class, orphanRemoval: true)]
    private Collection $traitements;

    #[ORM\Column(length: 255)]
    private ?string $type = null;


    #[ORM\OneToMany(mappedBy: 'employe', targetEntity: Conge::class, cascade: ['persist', 'remove'])]
    private Collection $conges;


    public function __construct()
    {
        $this->consultationsAsMedecin = new ArrayCollection();
        $this->consultationsAsInfirmier = new ArrayCollection();
        $this->stocks = new ArrayCollection();
        $this->rdvs = new ArrayCollection();
        $this->traitements = new ArrayCollection(); 
        $this->conges = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
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
    

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;
        return $this;
    }

    public function getFonction(): ?string
    {
        return $this->fonction;
    }

    public function setFonction(string $fonction): static
    {
        $this->fonction = $fonction;
        return $this;
    }

    public function getDateEmbauche(): ?\DateTimeInterface
    {
        return $this->dateEmbauche;
    }

    public function setDateEmbauche(\DateTimeInterface $dateEmbauche): static
    {
        $this->dateEmbauche = $dateEmbauche;
        return $this;
    }

    public function getComingDaysInWeek(): array
    {
        return $this->comingDaysInWeek;
    }

    public function setComingDaysInWeek(array $comingDaysInWeek): static
    {
        $this->comingDaysInWeek = $comingDaysInWeek;
        return $this;
    }

    public function isOnDaysOff(): bool
    {
        return $this->isOnDaysOff;
    }

    public function setIsOnDaysOff(bool $isOnDaysOff): static
    {
        $this->isOnDaysOff = $isOnDaysOff;
        return $this;
    }

    public function getTypeSalaire(): ?string
    {
        return $this->typeSalaire;
    }

    public function setTypeSalaire(string $typeSalaire): static
    {
        $this->typeSalaire = $typeSalaire;
        return $this;
    }

    public function getValeurSalaire(): ?float
    {
        return $this->valeurSalaire;
    }

    public function setValeurSalaire(float $valeurSalaire): static
    {
        $this->valeurSalaire = $valeurSalaire;
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

    public function getMatricule(): ?string
    {
        return $this->matricule;
    }

    public function setMatricule(?string $matricule): static
    {
        $this->matricule = $matricule;
        return $this;
    }

    public function getTypeContrat(): ?string
    {
        return $this->typeContrat;
    }

    public function setTypeContrat(string $typeContrat): static
    {
        $this->typeContrat = $typeContrat;
        return $this;
    }

    public function getDureeContrat(): ?int
    {
        return $this->dureeContrat;
    }

    public function setDureeContrat(?int $dureeContrat): static
    {
        $this->dureeContrat = $dureeContrat;
        return $this;
    }

    public function getAdministrativeFiles(): array
    {
        return $this->administrativeFiles;
    }

    public function setAdministrativeFiles(array $administrativeFiles): static
    {
        $this->administrativeFiles = $administrativeFiles;
        return $this;
    }

    public function addAdministrativeFile(string $filePath): static
    {
        if (!in_array($filePath, $this->administrativeFiles, true)) {
            $this->administrativeFiles[] = $filePath;
        }
        return $this;
    }

    public function removeAdministrativeFile(string $filePath): static
    {
        $this->administrativeFiles = array_filter(
            $this->administrativeFiles,
            fn($file) => $file !== $filePath
        );
        return $this;
    }

    /**
     * @return Collection<int, Consultation>
     */
    public function getConsultationsAsMedecin(): Collection
    {
        return $this->consultationsAsMedecin;
    }

    public function addConsultationAsMedecin(Consultation $consultation): static
    {
        if (!$this->consultationsAsMedecin->contains($consultation)) {
            $this->consultationsAsMedecin->add($consultation);
            $consultation->setMedecin($this);
        }
        return $this;
    }

    public function removeConsultationAsMedecin(Consultation $consultation): static
    {
        if ($this->consultationsAsMedecin->removeElement($consultation)) {
            if ($consultation->getMedecin() === $this) {
                $consultation->setMedecin(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Consultation>
     */
    public function getConsultationsAsInfirmier(): Collection
    {
        return $this->consultationsAsInfirmier;
    }

    public function addConsultationAsInfirmier(Consultation $consultation): static
    {
        if (!$this->consultationsAsInfirmier->contains($consultation)) {
            $this->consultationsAsInfirmier->add($consultation);
            $consultation->setInfirmier($this);
        }
        return $this;
    }

    public function removeConsultationAsInfirmier(Consultation $consultation): static
    {
        if ($this->consultationsAsInfirmier->removeElement($consultation)) {
            if ($consultation->getInfirmier() === $this) {
                $consultation->setInfirmier(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Stock>
     */
    public function getStocks(): Collection
    {
        return $this->stocks;
    }

    public function addStock(Stock $stock): static
    {
        if (!$this->stocks->contains($stock)) {
            $this->stocks->add($stock);
            $stock->setEmployee($this);
        }
        return $this;
    }

    public function removeStock(Stock $stock): static
    {
        if ($this->stocks->removeElement($stock)) {
            if ($stock->getEmployee() === $this) {
                $stock->setEmployee(null);
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
            $rdv->setMedecin($this);
        }
        return $this;
    }

    public function removeRdv(Rdv $rdv): static
    {
        if ($this->rdvs->removeElement($rdv)) {
            if ($rdv->getMedecin() === $this) {
                $rdv->setMedecin(null);
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
            $traitement->setMedecin($this);
        }
        return $this;
    }

    public function removeTraitement(Traitement $traitement): static
    {
        if ($this->traitements->removeElement($traitement)) {
            if ($traitement->getMedecin() === $this) {
                $traitement->setMedecin(null);
            }
        }
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    

    public function getConges(): Collection
    {
        return $this->conges;
    }

    public function addConge(Conge $conge): self
    {
        if (!$this->conges->contains($conge)) {
            $this->conges[] = $conge;
            $conge->setEmploye($this);
        }
        return $this;
    }

    public function removeConge(Conge $conge): self
    {
        if ($this->conges->removeElement($conge)) {
            if ($conge->getEmploye() === $this) {
                $conge->setEmploye(null);
            }
        }
        return $this;
    }

}
