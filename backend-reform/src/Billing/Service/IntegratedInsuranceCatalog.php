<?php

namespace App\Billing\Service;

use App\Billing\Entity\Assurance;
use App\Billing\Repository\AssuranceRepository;
use Doctrine\ORM\EntityManagerInterface;

class IntegratedInsuranceCatalog
{
    public const CODE_SBN = 'SBN';
    public const CODE_BLEUES = 'BLEUES';

    /** @return array<int, array<string, mixed>> */
    public function getCatalog(): array
    {
        return [
            [
                'code' => self::CODE_SBN,
                'nom' => 'Sabunyuman',
                'logoPath' => '/assurances/sbn-logo.png',
                'website' => null,
                'email' => null,
                'formSchema' => [
                    'fields' => [
                        ['key' => 'societe', 'label' => 'Societe', 'type' => 'text', 'required' => false],
                        ['key' => 'assureNom', 'label' => 'Nom de l\'assure', 'type' => 'text', 'required' => true, 'source' => 'patient.fullName'],
                        ['key' => 'assureNumero', 'label' => 'Numero de l\'assure', 'type' => 'text', 'required' => true],
                        ['key' => 'beneficiaireNom', 'label' => 'Nom du beneficiaire', 'type' => 'text', 'required' => true],
                        ['key' => 'beneficiaireNumero', 'label' => 'Numero du beneficiaire', 'type' => 'text', 'required' => true],
                        ['key' => 'sexe', 'label' => 'Sexe', 'type' => 'text', 'required' => true, 'source' => 'patient.sexe'],
                        ['key' => 'coverageRate', 'label' => 'Taux de couverture', 'type' => 'number', 'required' => true],
                    ],
                ],
            ],
            [
                'code' => self::CODE_BLEUES,
                'nom' => 'Les Assurances Bleues',
                'logoPath' => '/assurances/assurancesbleues-logo.png',
                'website' => null,
                'email' => null,
                'formSchema' => [
                    'fields' => [
                        ['key' => 'souscripteur', 'label' => 'Souscripteur', 'type' => 'text', 'required' => true],
                        ['key' => 'salarieNomPrenom', 'label' => 'Salarie - Nom et prenom', 'type' => 'text', 'required' => true],
                        ['key' => 'salarieMatricule', 'label' => 'Salarie - Matricule', 'type' => 'text', 'required' => true],
                        ['key' => 'patientNomPrenom', 'label' => 'Patient - Nom et prenom', 'type' => 'text', 'required' => true, 'source' => 'patient.fullName'],
                        ['key' => 'patientMatricule', 'label' => 'Patient - Matricule', 'type' => 'text', 'required' => true],
                        ['key' => 'patientAge', 'label' => 'Patient - Age', 'type' => 'number', 'required' => true, 'source' => 'patient.age'],
                        ['key' => 'patientSexe', 'label' => 'Patient - Sexe', 'type' => 'text', 'required' => true, 'source' => 'patient.sexe'],
                        ['key' => 'coverageRate', 'label' => 'Taux de couverture', 'type' => 'number', 'required' => true],
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public function getByCode(string $code): ?array
    {
        foreach ($this->getCatalog() as $item) {
            if (strtoupper((string) ($item['code'] ?? '')) === strtoupper($code)) {
                return $item;
            }
        }

        return null;
    }

    /** @return Assurance[] */
    public function syncCatalog(AssuranceRepository $assuranceRepository, EntityManagerInterface $em): array
    {
        $result = [];
        foreach ($this->getCatalog() as $definition) {
            $code = (string) ($definition['code'] ?? '');
            if ($code === '') {
                continue;
            }

            $assurance = $assuranceRepository->findOneByCode($code) ?? new Assurance();
            $assurance
                ->setCode($code)
                ->setNom((string) ($definition['nom'] ?? $code))
                ->setLogoPath(isset($definition['logoPath']) ? (string) $definition['logoPath'] : null)
                ->setWebsite(isset($definition['website']) ? (string) $definition['website'] : null)
                ->setEmail(isset($definition['email']) ? (string) $definition['email'] : null)
                ->setFormSchema(is_array($definition['formSchema'] ?? null) ? $definition['formSchema'] : []);

            if ($assurance->getId() === null) {
                $assurance->setActif(false);
            }

            $em->persist($assurance);
            $result[] = $assurance;
        }

        $em->flush();

        return $result;
    }
}
