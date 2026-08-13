<?php

namespace App\DataFixtures;

use App\IdentityAccess\Entity\Employe;
use App\Patient\Entity\Patient;
use App\IdentityAccess\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $this->createAdminUser($manager);
        $this->createEmployeesWithUsers($manager);
        $this->createPatientsWithUsers($manager);
        $this->createModeDePaiement($manager);

        $manager->flush();
    }

    private function createAdminUser(ObjectManager $manager): void
    {
        $admin = new User();
        $admin->setUsername('admin');
        $admin->setPassword($this->passwordHasher->hashPassword($admin, '123'));
        $admin->setRoles(['ROLE_ADMIN']);

        $manager->persist($admin);
    }

    private function createEmployeesWithUsers(ObjectManager $manager): void
    {
        $workingDays = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi'];
        $dateEmbauche = new \DateTimeImmutable('2025-01-01');

        $employeeDefinitions = [
            // 3 medecins: 1 fixe, 2 pourcentage
            [
                'username' => 'medecin1',
                'roles' => ['ROLE_MEDECIN'],
                'nom' => 'Diallo',
                'prenom' => 'Awa',
                'telephone' => '770000001',
                'fonction' => 'Medecin dentiste',
                'type' => 'Medecin',
                'email' => 'medecin1@dentalsoft.local',
                'matricule' => 'EMP-MED-001',
                'typeContrat' => 'CDI',
                'dureeContrat' => null,
                'typeSalaire' => 'fixe',
                'valeurSalaire' => 180000.00,
            ],
            [
                'username' => 'medecin2',
                'roles' => ['ROLE_MEDECIN'],
                'nom' => 'Faye',
                'prenom' => 'Moussa',
                'telephone' => '770000002',
                'fonction' => 'Medecin dentiste',
                'type' => 'Medecin',
                'email' => 'medecin2@dentalsoft.local',
                'matricule' => 'EMP-MED-002',
                'typeContrat' => 'CDI',
                'dureeContrat' => null,
                'typeSalaire' => 'pourcentage',
                'valeurSalaire' => 35.00,
            ],
            [
                'username' => 'medecin3',
                'roles' => ['ROLE_MEDECIN'],
                'nom' => 'Ndiaye',
                'prenom' => 'Fatou',
                'telephone' => '770000003',
                'fonction' => 'Medecin dentiste',
                'type' => 'Medecin',
                'email' => 'medecin3@dentalsoft.local',
                'matricule' => 'EMP-MED-003',
                'typeContrat' => 'CDI',
                'dureeContrat' => null,
                'typeSalaire' => 'pourcentage',
                'valeurSalaire' => 30.00,
            ],

            // 8 infirmiers
            [
                'username' => 'infirmier1',
                'roles' => ['ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE', 'ROLE_SECRETAIRE'],
                'nom' => 'Sarr',
                'prenom' => 'Cheikh',
                'telephone' => '770000011',
                'fonction' => 'Infirmier',
                'type' => 'Infirmier',
                'email' => 'infirmier1@dentalsoft.local',
                'matricule' => 'EMP-INF-001',
                'typeContrat' => 'CDI',
                'dureeContrat' => null,
                'typeSalaire' => 'fixe',
                'valeurSalaire' => 90000.00,
            ],
            [
                'username' => 'infirmier2',
                'roles' => ['ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE', 'ROLE_SECRETAIRE'],
                'nom' => 'Ba',
                'prenom' => 'Mariama',
                'telephone' => '770000012',
                'fonction' => 'Infirmier',
                'type' => 'Infirmier',
                'email' => 'infirmier2@dentalsoft.local',
                'matricule' => 'EMP-INF-002',
                'typeContrat' => 'CDI',
                'dureeContrat' => null,
                'typeSalaire' => 'fixe',
                'valeurSalaire' => 92000.00,
            ],
            [
                'username' => 'infirmier3',
                'roles' => ['ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE', 'ROLE_SECRETAIRE'],
                'nom' => 'Kane',
                'prenom' => 'Ibrahima',
                'telephone' => '770000013',
                'fonction' => 'Infirmier',
                'type' => 'Infirmier',
                'email' => 'infirmier3@dentalsoft.local',
                'matricule' => 'EMP-INF-003',
                'typeContrat' => 'CDI',
                'dureeContrat' => null,
                'typeSalaire' => 'fixe',
                'valeurSalaire' => 95000.00,
            ],
            [
                'username' => 'infirmier4',
                'roles' => ['ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE', 'ROLE_SECRETAIRE'],
                'nom' => 'Gueye',
                'prenom' => 'Aminata',
                'telephone' => '770000014',
                'fonction' => 'Infirmier',
                'type' => 'Infirmier',
                'email' => 'infirmier4@dentalsoft.local',
                'matricule' => 'EMP-INF-004',
                'typeContrat' => 'CDI',
                'dureeContrat' => null,
                'typeSalaire' => 'fixe',
                'valeurSalaire' => 93000.00,
            ],
            [
                'username' => 'infirmier5',
                'roles' => ['ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE', 'ROLE_SECRETAIRE'],
                'nom' => 'Diop',
                'prenom' => 'Abdou',
                'telephone' => '770000015',
                'fonction' => 'Infirmier',
                'type' => 'Infirmier',
                'email' => 'infirmier5@dentalsoft.local',
                'matricule' => 'EMP-INF-005',
                'typeContrat' => 'CDI',
                'dureeContrat' => null,
                'typeSalaire' => 'fixe',
                'valeurSalaire' => 91000.00,
            ],
            [
                'username' => 'infirmier6',
                'roles' => ['ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE', 'ROLE_SECRETAIRE'],
                'nom' => 'Sow',
                'prenom' => 'Ndeye',
                'telephone' => '770000016',
                'fonction' => 'Infirmier',
                'type' => 'Infirmier',
                'email' => 'infirmier6@dentalsoft.local',
                'matricule' => 'EMP-INF-006',
                'typeContrat' => 'CDI',
                'dureeContrat' => null,
                'typeSalaire' => 'fixe',
                'valeurSalaire' => 90500.00,
            ],
            [
                'username' => 'infirmier7',
                'roles' => ['ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE', 'ROLE_SECRETAIRE'],
                'nom' => 'Cisse',
                'prenom' => 'Mamadou',
                'telephone' => '770000017',
                'fonction' => 'Infirmier',
                'type' => 'Infirmier',
                'email' => 'infirmier7@dentalsoft.local',
                'matricule' => 'EMP-INF-007',
                'typeContrat' => 'CDI',
                'dureeContrat' => null,
                'typeSalaire' => 'fixe',
                'valeurSalaire' => 94000.00,
            ],
            [
                'username' => 'infirmier8',
                'roles' => ['ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE', 'ROLE_SECRETAIRE'],
                'nom' => 'Thiam',
                'prenom' => 'Rokhaya',
                'telephone' => '770000018',
                'fonction' => 'Infirmier',
                'type' => 'Infirmier',
                'email' => 'infirmier8@dentalsoft.local',
                'matricule' => 'EMP-INF-008',
                'typeContrat' => 'CDI',
                'dureeContrat' => null,
                'typeSalaire' => 'fixe',
                'valeurSalaire' => 91500.00,
            ],

            // 1 receptionniste
            [
                'username' => 'reception1',
                'roles' => ['ROLE_RECEPTIONNISTE', 'ROLE_RECEPTION', 'ROLE_SECRETAIRE'],
                'nom' => 'Lo',
                'prenom' => 'Aissatou',
                'telephone' => '770000021',
                'fonction' => 'Receptionniste',
                'type' => 'Receptionniste',
                'email' => 'reception1@dentalsoft.local',
                'matricule' => 'EMP-REC-001',
                'typeContrat' => 'CDI',
                'dureeContrat' => null,
                'typeSalaire' => 'fixe',
                'valeurSalaire' => 85000.00,
            ],
        ];

        foreach ($employeeDefinitions as $definition) {
            $user = new User();
            $user->setUsername($definition['username']);
            $user->setPassword($this->passwordHasher->hashPassword($user, '123'));
            $user->setRoles($definition['roles']);

            $employee = new Employe();
            $employee->setUser($user);
            $employee->setNom($definition['nom']);
            $employee->setPrenom($definition['prenom']);
            $employee->setTelephone($definition['telephone']);
            $employee->setFonction($definition['fonction']);
            $employee->setDateEmbauche($dateEmbauche);
            $employee->setComingDaysInWeek($workingDays);
            $employee->setIsOnDaysOff(false);
            $employee->setTypeSalaire($definition['typeSalaire']);
            $employee->setValeurSalaire($definition['valeurSalaire']);
            $employee->setEmail($definition['email']);
            $employee->setMatricule($definition['matricule']);
            $employee->setTypeContrat($definition['typeContrat']);
            $employee->setDureeContrat($definition['dureeContrat']);
            $employee->setAdministrativeFiles([]);
            $employee->setType($definition['type']);

            $manager->persist($user);
            $manager->persist($employee);
        }
    }

    private function createPatientsWithUsers(ObjectManager $manager): void
    {
        $patients = [
            [
                'username' => 'patient1',
                'nom' => 'Sene',
                'prenom' => 'Aly',
                'dateNaissance' => '1991-04-10',
                'sexe' => 'Homme',
                'telephone' => '780000001',
                'email' => 'patient1@dentalsoft.local',
                'profession' => 'Comptable',
                'lieuNaissance' => 'Dakar',
                'adresse' => 'Parcelles Assainies, Dakar',
                'numCarnet' => 'P-FIX-001',
                'groupeSanguin' => 'O+',
                'referencement' => 'Fixture',
            ],
            [
                'username' => 'patient2',
                'nom' => 'Ndao',
                'prenom' => 'Rama',
                'dateNaissance' => '1988-09-22',
                'sexe' => 'Femme',
                'telephone' => '780000002',
                'email' => 'patient2@dentalsoft.local',
                'profession' => 'Enseignante',
                'lieuNaissance' => 'Thies',
                'adresse' => 'Keur Massar, Dakar',
                'numCarnet' => 'P-FIX-002',
                'groupeSanguin' => 'A+',
                'referencement' => 'Fixture',
            ],
            [
                'username' => 'patient3',
                'nom' => 'Sy',
                'prenom' => 'Mamadou',
                'dateNaissance' => '2000-01-14',
                'sexe' => 'Homme',
                'telephone' => '780000003',
                'email' => 'patient3@dentalsoft.local',
                'profession' => 'Etudiant',
                'lieuNaissance' => 'Saint-Louis',
                'adresse' => 'Medina, Dakar',
                'numCarnet' => 'P-FIX-003',
                'groupeSanguin' => 'B+',
                'referencement' => 'Fixture',
            ],
            [
                'username' => 'patient4',
                'nom' => 'Fall',
                'prenom' => 'Adama',
                'dateNaissance' => '1979-06-30',
                'sexe' => 'Femme',
                'telephone' => '780000004',
                'email' => 'patient4@dentalsoft.local',
                'profession' => 'Commercante',
                'lieuNaissance' => 'Kaolack',
                'adresse' => 'Grand Yoff, Dakar',
                'numCarnet' => 'P-FIX-004',
                'groupeSanguin' => 'AB+',
                'referencement' => 'Fixture',
            ],
            [
                'username' => 'patient5',
                'nom' => 'Diagne',
                'prenom' => 'Ousmane',
                'dateNaissance' => '1995-12-03',
                'sexe' => 'Homme',
                'telephone' => '780000005',
                'email' => 'patient5@dentalsoft.local',
                'profession' => 'Informaticien',
                'lieuNaissance' => 'Mbour',
                'adresse' => 'Ouakam, Dakar',
                'numCarnet' => 'P-FIX-005',
                'groupeSanguin' => 'O-',
                'referencement' => 'Fixture',
            ],
        ];

        foreach ($patients as $definition) {
            $user = new User();
            $user->setUsername($definition['username']);
            $user->setPassword($this->passwordHasher->hashPassword($user, '123'));
            $user->setRoles(['ROLE_PATIENT']);

            $patient = new Patient();
            $patient->setNom($definition['nom']);
            $patient->setPrenom($definition['prenom']);
            $patient->setDateNaissance(new \DateTimeImmutable($definition['dateNaissance']));
            $patient->setDateInscription(new \DateTimeImmutable('now'));
            $patient->setSexe($definition['sexe']);
            $patient->setTelephone($definition['telephone']);
            $patient->setEmail($definition['email']);
            $patient->setProfession($definition['profession']);
            $patient->setLieuNaissance($definition['lieuNaissance']);
            $patient->setAdresse($definition['adresse']);
            $patient->setNumCarnet($definition['numCarnet']);
            $patient->setGroupeSanguin($definition['groupeSanguin']);
            $patient->setReferencement($definition['referencement']);
            $patient->setPortalUser($user);

            $manager->persist($user);
            $manager->persist($patient);
        }
    }

    private function createModeDePaiement(ObjectManager $manager): void
    {
        $mode_definitions = [
            [
                'libelle' => 'Espèces',
                'type' => 'cash',
                'actif' => true,
                'coverageRate' => null,
                'notes' => 'Paiement en espèces',
            ],
            [
                'libelle' => "Orange Mobile Money",
                'type' => 'mobilemoney',
                'actif' => true,
                'coverageRate' => null,
                'notes' => 'Paiement via Mobile Money',
            ],
            [
                'libelle' => 'Virement bancaire',
                'type' => 'transfer',
                'actif' => true,
                'coverageRate' => null,
                'notes' => 'Paiement via virement bancaire',
            ],
        ];

        foreach ($mode_definitions as $def) {
            $mode = new \App\Billing\Entity\ModeDePaiement();
            $mode->setLibelle($def['libelle']);
            $mode->setType($def['type']);
            $mode->setActif($def['actif']);
            $mode->setCoverageRate($def['coverageRate']);
            $mode->setNotes($def['notes']);

            $manager->persist($mode);
        }
    }
}
