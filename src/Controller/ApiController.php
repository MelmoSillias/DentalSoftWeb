<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Consommable;
use App\Entity\Employe;
use App\Entity\Rdv;
use App\Entity\Facture;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\EmployeRepository;
use App\Repository\FactureRepository;
use App\Repository\PatientRepository;
use App\Repository\SalleRepository;
use App\Repository\MedecinRepository;
use App\Repository\RdvRepository;
use App\Repository\UserRepository;
use CalendarBundle\Entity\Event;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\MakerBundle\EventRegistry;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class ApiController extends AbstractController
{



    #[Route('/api/rdv/create', name: 'api_rdv_create', methods: ['POST'])]
    public function createRdv(
        Request $request,
        PatientRepository $patientRepo,
        SalleRepository $salleRepo,
        EmployeRepository $medecinRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $patient = $patientRepo->find($data['patient_id']);
        $salle = $salleRepo->find($data['salle_id']);
        $medecin = $medecinRepo->find($data['medecin_id']);

        if (!$patient || !$salle || !$medecin) {
            return new JsonResponse(['success' => false, 'error' => 'Données invalides'], 400);
        }

        $rdv = new Rdv();
        $rdv->setPatient($patient)
            ->setSalle($salle)
            ->setMedecin($medecin)
            ->setDescription($data['description'])
            ->setStatut(0)
            ->setDateCreation(new \DateTime())
            ->setDateRdv(new \DateTime($data['date'] . ' ' . $data['time']));

        $em->persist($rdv);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }


    // Dans votre contrôleur, par exemple RdvController.php
    #[Route('/api/rdvs/stats/{date}', name: 'api_rdvs_stats', methods: ['GET'])]
    public function stats(string $date, RdvRepository $rdvRepo): JsonResponse {
        $selectedDate = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$selectedDate) {
            return new JsonResponse(['success' => false, 'error' => 'Date invalide'], 400);
        }
        $start = new \DateTime($date . ' 00:00:00');
        $end = new \DateTime($date . ' 23:59:59');
    
        $pending = $rdvRepo->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.statut = 0')
            ->andWhere('r.dateRdv BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    
        $validated = $rdvRepo->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.statut = 1')
            ->andWhere('r.dateRdv BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    
        $postponed = $rdvRepo->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.statut = -1')
            ->andWhere('r.dateRdv BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    
        $cancelled = $rdvRepo->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.statut = -2')
            ->andWhere('r.dateRdv BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    
        return new JsonResponse([
             'pending' => (int)$pending,
             'validated' => (int)$validated,
             'postponed' => (int)$postponed,
             'cancelled' => (int)$cancelled,
        ]);
    }
    

#[Route('/api/rdv/report', name: 'api_rdv_report', methods: ['POST'])]
public function reportRdv(
    Request $request,
    RdvRepository $rdvRepo,
    EntityManagerInterface $em
): JsonResponse {
    $data = json_decode($request->getContent(), true);
    if (!isset($data['rdv_id'], $data['new_date'], $data['new_time'])) {
        return new JsonResponse(['success' => false, 'error' => 'Paramètres manquants'], 400);
    }

    $originalRdv = $rdvRepo->find($data['rdv_id']);
    if (!$originalRdv) {
        return new JsonResponse(['success' => false, 'error' => 'RDV non trouvé'], 404);
    }

    // Met à jour l'ancien RDV avec le statut "reporté" (-1)
    $originalRdv->setStatut(-1);

    // Crée un nouveau RDV avec les mêmes informations que l'ancien mais avec la nouvelle date/heure
    $newRdv = new Rdv();
    $newRdv->setPatient($originalRdv->getPatient())
           ->setSalle($originalRdv->getSalle())
           ->setMedecin($originalRdv->getMedecin())
           ->setDescription($originalRdv->getDescription())
           ->setStatut(0) // nouveau RDV en attente
           ->setDateCreation(new \DateTime())
           ->setDateRdv(new \DateTime($data['new_date'] . ' ' . $data['new_time']));

    $em->persist($newRdv);
    $em->flush();

    return new JsonResponse(['success' => true]);
}

    #[Route('/api/rdv/update_status', name: 'api_rdv_update_status', methods: ['POST'])]
public function updateStatus(Request $request, RdvRepository $rdvRepo, EntityManagerInterface $em): JsonResponse {
    $data = json_decode($request->getContent(), true);
    $rdv = $rdvRepo->find($data['rdv_id']);
    if (!$rdv) {
        return new JsonResponse(['success' => false, 'error' => 'RDV non trouvé'], 404);
    }
    $newStatus = $data['status'];
    $rdv->setStatut($newStatus);
    $em->flush();
    return new JsonResponse(['success' => true]);
}



    #[Route('/api/salles', name: 'api_salles', methods: ['GET'])]
    public function getSalles(SalleRepository $salleRepo): JsonResponse
    {
        $salles = $salleRepo->findAll();

        if (!$salles) {
            return new JsonResponse([], 200); // Retourne un tableau vide si aucune salle n'est trouvée
        }

        $data = array_map(function ($salle) {
            return [
                'id' => $salle->getId(),
                'nom' => $salle->getNom(),
                'description' => $salle->getDescription(),
            ];
        }, $salles);

        return new JsonResponse($data);
    }

    #[Route('/api/medecins', name: 'api_medecins', methods: ['GET'])]
    public function getMedecins(EmployeRepository $medecinRepo): JsonResponse
    {
        $medecins = $medecinRepo->findBy(['type' => 'medecin']); // Suppose que le type "medecin" est utilisé

        if (!$medecins) {
            return new JsonResponse([], 200); // Retourne un tableau vide si aucun médecin n'est trouvé
        }

        $data = array_map(function ($medecin) {
            return [
                'id' => $medecin->getId(),
                'nom' => $medecin->getNom(),
                'prenom' => $medecin->getPrenom(),
            ];
        }, $medecins);

        return new JsonResponse($data);
    }

    #[Route('/api/rdvs/{date}', name: 'api_rdvs', methods: ['GET'])]
    public function getRdvs(Request $request, string $date ,EntityManagerInterface $em): JsonResponse
        {
            if (!$date) {
                return new JsonResponse(['success' => false, 'error' => 'Date non fournie'], 400);
            }

            $dateObj = \DateTime::createFromFormat('Y-m-d', $date);
            if (!$dateObj) {
                return new JsonResponse(['success' => false, 'error' => 'Format de date invalide'], 400);
            }

            $rdvRepository = $em->getRepository(Rdv::class);
            $rdvs = $rdvRepository->findRdvByDate($dateObj);

            $data = array_map(function ($rdv) {
                return [
                    'id' => $rdv->getId(),
                    'patient' => $rdv->getPatient()->getNom() . ' ' . $rdv->getPatient()->getPrenom(),
                    'salle' => $rdv->getSalle()->getid(),
                    'medecin' => $rdv->getMedecin()->getNom() . ' ' . $rdv->getMedecin()->getPrenom(),
                    'description' => $rdv->getDescription(),
                    'statut' => $rdv->getStatut(),
                    'dateRdv' => $rdv->getDateRdv()->format('Y-m-d H:i:s'),
                    'dateCreation' => $rdv->getDateCreation()->format('d-m-Y H:i:s'),
                ];
            }, $rdvs);
            return new JsonResponse($data);
        }
    
    #[Route('/api/events/all')]
    public function GetEvents(BookingRepository $BookRep): JsonResponse {
        $bookings = $BookRep->findAll();
        $data = array_map(function (Booking $booking) {
            return [
                'id' => $booking->getId(),
                'title' => $booking->getTitle(),
                'description' => $booking->getDescription(),
                'beginAt' => $booking->getBeginAt()->format('Y-m-d\TH:i:s'),
                'endAt' => $booking->getEndAt()?->format('Y-m-d\TH:i:s'),
                'statut' => $booking->getStatut(),
            ];
        }, $bookings);

        return new JsonResponse($data);
    }

    #[Route('/api/event/createBooking', name: 'api_event_create_booking', methods: ['POST', 'GET'])]
    public function createBooking(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['beginAt'], $data['title'])) {
            return new JsonResponse(['success' => false, 'error' => 'Données manquantes'], 400);
        }

        $beginAt = \DateTime::createFromFormat('Y-m-d H:i:s', $data['beginAt']);
        if (isset($data['endAt'])){
            $endAt = \DateTime::createFromFormat('Y-m-d H:i:s', $data['endAt']);
            
        }else{$endAt = null;

        }
            

        if (!$beginAt) {
            return new JsonResponse(['success' => false, 'error' => 'Format de date invalide pour beginAt'], 400);
        }

        if ($endAt && $endAt < $beginAt) {
            return new JsonResponse(['success' => false, 'error' => 'endAt ne peut pas être avant beginAt'], 400);
        }

        $booking = new Booking();
        $booking->setBeginAt($beginAt)
            ->setEndAt($endAt)
            ->setTitle($data['title'])
            ->setDescription($data['description'] ?? null);

        $em->persist($booking);
        $em->flush();

        return new JsonResponse(['success' => true, 'id' => $booking->getId()]);
    }

    #[Route('/api/users/create', name: 'api_users_create', methods: ['POST'])]
    public function create(
        Request $request, 
        EntityManagerInterface $em, 
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['username'])) {
            return new JsonResponse(['success' => false, 'error' => 'Username manquant'], 400);
        }

        // Vérifier si le username est déjà utilisé
        $existingUser = $em->getRepository(User::class)->findOneBy(['username' => $data['username']]);
        if ($existingUser) {
            return new JsonResponse(['success' => false, 'error' => 'Nom d\'utilisateur déjà utilisé'], 400);
        }

        $user = new User();
        $user->setUsername($data['username']);
        // Par défaut, on ne reçoit pas de mot de passe dans la création ; on peut définir un mot de passe par défaut
        $defaultPassword = 'password'; // À modifier selon vos besoins
        $hashedPassword = $passwordHasher->hashPassword($user, $defaultPassword);
        $user->setPassword($hashedPassword);
        // Si vous souhaitez lier un employé, vous pouvez le faire ici (ex: $user->setEmployee($employee))
        $em->persist($user);
        $em->flush();

        return new JsonResponse(['success' => true, 'user_id' => $user->getId()]);
    }

    #[Route('/api/users/update', name: 'api_users_update', methods: ['POST'])]
    public function update(
        Request $request, 
        UserRepository $userRepository, 
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['user_id'], $data['username'])) {
            return new JsonResponse(['success' => false, 'error' => 'Paramètres manquants'], 400);
        }
        $user = $userRepository->find($data['user_id']);
        if (!$user) {
            return new JsonResponse(['success' => false, 'error' => 'Utilisateur non trouvé'], 404);
        }
        $user->setUsername($data['username']);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/api/users/reset_password', name: 'api_users_reset_password', methods: ['POST'])]
    public function resetPassword(
        Request $request, 
        UserRepository $userRepository, 
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['user_id'], $data['password'])) {
            return new JsonResponse(['success' => false, 'error' => 'Paramètres manquants'], 400);
        }
        $user = $userRepository->find($data['user_id']);
        if (!$user) {
            return new JsonResponse(['success' => false, 'error' => 'Utilisateur non trouvé'], 404);
        }
        $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/api/users/delete', name: 'api_users_delete', methods: ['POST'])]
    public function delete(
        Request $request, 
        UserRepository $userRepository, 
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['user_id'])) {
            return new JsonResponse(['success' => false, 'error' => 'Paramètre user_id manquant'], 400);
        }
        $user = $userRepository->find($data['user_id']);
        if (!$user) {
            return new JsonResponse(['success' => false, 'error' => 'Utilisateur non trouvé'], 404);
        }
        $em->remove($user);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/api/reports', name: 'api_reports_data', methods: ['GET'])]
    public function reports(
        Request $request
        // Uncomment and use the repositories as needed:
        // , ConsultationRepository $consultationRepository,
        // FactureRepository $factureRepository,
        // PatientRepository $patientRepository,
        // EmployeeRepository $employeeRepository,
        // AppointmentRepository $appointmentRepository
    ): JsonResponse {
        // Retrieve query parameters for period and custom date ranges
        $period = $request->query->get('period', 'month');
        $employeeId = $request->query->get('employeeId', null);
        $customStart = $request->query->get('start');
        $customEnd = $request->query->get('end');

        $now = new DateTime();
        if ($period === 'custom' && $customStart && $customEnd) {
            $startDate = new DateTime($customStart);
            $endDate   = (new DateTime($customEnd))->setTime(23, 59, 59);
        } else {
            switch ($period) {
                case 'today':
                    $startDate = (clone $now)->setTime(0, 0, 0);
                    $endDate   = (clone $now)->setTime(23, 59, 59);
                    break;
                case 'week':
                    // Last 7 days (including today)
                    $startDate = (clone $now)->modify('-6 days')->setTime(0, 0, 0);
                    $endDate   = (clone $now)->setTime(23, 59, 59);
                    break;
                case 'year':
                    $startDate = (clone $now)->modify('-11 months')->setTime(0, 0, 0);
                    $endDate   = (clone $now)->setTime(23, 59, 59);
                    break;
                case 'month':
                default:
                    // Last 30 days by default
                    $startDate = (clone $now)->modify('-29 days')->setTime(0, 0, 0);
                    $endDate   = (clone $now)->setTime(23, 59, 59);
                    break;
            }
        }

        // ----------------------------------------------------------------
        // Prepare the report data using your repository methods.
        // Replace the dummy values below with calls to your repositories.

        // 1. Employee Statistics
        $employees = [];
        // Example pseudocode: if filtering by employee, return that one;
        // else, return all with computed stats.
        /*
        if ($employeeId) {
            $employee = $employeeRepository->find($employeeId);
            $employees[] = [
                'name' => $employee->getNom(),
                'role' => $employee->getRole(),
                'consultations' => $consultationRepository->countByEmployeeAndPeriod($employee, $startDate, $endDate),
                'patients' => $consultationRepository->countUniquePatientsByEmployee($employee, $startDate, $endDate),
                'avgTime' => $consultationRepository->getAverageConsultationTimeByEmployee($employee, $startDate, $endDate),
                'revenue' => $factureRepository->sumRevenueByEmployee($employee, $startDate, $endDate),
            ];
        } else {
            $allEmployees = $employeeRepository->findAll();
            foreach ($allEmployees as $employee) {
                $employees[] = [
                    'name' => $employee->getNom(),
                    'role' => $employee->getRole(),
                    'consultations' => $consultationRepository->countByEmployeeAndPeriod($employee, $startDate, $endDate),
                    'patients' => $consultationRepository->countUniquePatientsByEmployee($employee, $startDate, $endDate),
                    'avgTime' => $consultationRepository->getAverageConsultationTimeByEmployee($employee, $startDate, $endDate),
                    'revenue' => $factureRepository->sumRevenueByEmployee($employee, $startDate, $endDate),
                ];
            }
        }
        */
        // For demonstration, use dummy data:
        $employees = [
            [
                'name' => 'Dr. Jean Dupont',
                'role' => 'Medecin',
                'consultations' => 20,
                'patients' => 18,
                'avgTime' => 30,
                'revenue' => 500000,
            ],
            [
                'name' => 'Infirmier Marie',
                'role' => 'Infirmier',
                'consultations' => 15,
                'patients' => 14,
                'avgTime' => 25,
                'revenue' => 250000,
            ]
        ];

        // 2. Role breakdown data
        $rolesData = [
            'Medecin' => 5,
            'Infirmier' => 3,
        ];

        // 3. Financial Data
        $finances = [
            'revenue' => 1200000,
            'expenses' => 300000,
            'net' => 900000,
            'unpaidCount' => 2,
            'unpaidAmount' => 15000,
        ];

        // 4. Revenue trend over time (e.g., daily revenue for the period)
        $revenueTrend = [
            ['date' => '2025-04-01', 'amount' => 40000],
            ['date' => '2025-04-02', 'amount' => 50000],
            // Add additional daily data as needed
        ];

        // 5. Patients data: gender, age groups, regional distribution, etc.
        $patientsData = [
            'male' => 50,
            'female' => 45,
            'ageGroups' => [
                '<18' => 5,
                '18-30' => 20,
                '31-50' => 30,
                '51+' => 40,
            ],
            'regions' => [
                ['region' => 'Paris', 'count' => 60],
                ['region' => 'Lyon', 'count' => 20],
            ],
        ];

        // 6. Appointments & Consultations trend data
        $appointmentsTrend = [
            ['date' => '2025-04-01', 'appointments' => 10, 'consultations' => 8],
            ['date' => '2025-04-02', 'appointments' => 12, 'consultations' => 9],
            // More data points...
        ];
        $attendanceRate = 80; // Example: 80%
        $noShows = 3;

        // Assemble all data into a structured response
        $data = [
            'employees'         => $employees,
            'roles'             => $rolesData,
            'finances'          => $finances,
            'revenueTrend'      => $revenueTrend,
            'patients'          => $patientsData,
            'appointmentsTrend' => $appointmentsTrend,
            'attendanceRate'    => $attendanceRate,
            'noShows'           => $noShows,
        ];

        return new JsonResponse($data);
    }


}
