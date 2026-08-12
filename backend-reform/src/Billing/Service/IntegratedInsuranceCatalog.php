<?php

namespace App\Billing\Service;

use App\Billing\Infrastructure\Persistence\Doctrine\Entity\Assurance;
use App\Billing\Infrastructure\Persistence\Doctrine\Repository\AssuranceRepository;
use Doctrine\ORM\EntityManagerInterface;

class IntegratedInsuranceCatalog
{
    public const CODE_SBN = 'SBN';
    public const CODE_BLEUES = 'BLEUES';
    public const CODE_SUNU = 'SUNU';
    public const CODE_LAFIA = 'LAFIA';
    public const CODE_SAHAM = 'SAHAM';
    public const CODE_MSH = 'MSH';

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
            [
                'code' => self::CODE_SUNU,
                'nom' => 'SUNU Assurances',
                'logoPath' => '/assurances/sunu-logo.png',
                'website' => null,
                'email' => null,
                'formSchema' => [
                    'fields' => [
                        ['key' => 'carteNumero', 'label' => 'Carte N°', 'type' => 'text', 'required' => true],
                        ['key' => 'societe', 'label' => 'Societe', 'type' => 'text', 'required' => false],
                        ['key' => 'numeroPolice', 'label' => 'N° police', 'type' => 'text', 'required' => true],
                        ['key' => 'titulaireNomPrenoms', 'label' => 'Titulaire - Nom et prenoms', 'type' => 'text', 'required' => true, 'source' => 'patient.fullName'],
                        ['key' => 'assurePrincipalNom', 'label' => 'Assure principal - Nom et prenoms', 'type' => 'text', 'required' => true],
                        ['key' => 'assurePrincipalTel', 'label' => 'Assure principal - N° tel', 'type' => 'text', 'required' => false],
                        ['key' => 'coverageRate', 'label' => 'Taux de couverture', 'type' => 'number', 'required' => true],
                    ],
                ],
            ],
            [
                'code' => self::CODE_LAFIA,
                'nom' => 'LAFIA',
                'logoPath' => '/assurances/lafia-logo.png',
                'website' => null,
                'email' => null,
                'formSchema' => [
                    'fields' => [
                        ['key' => 'numeroPolice', 'label' => 'Police', 'type' => 'text', 'required' => true],
                        ['key' => 'avenant', 'label' => 'Avenant', 'type' => 'text', 'required' => false],
                        ['key' => 'numeroAssure', 'label' => 'N° assuré', 'type' => 'text', 'required' => true],
                        ['key' => 'souscripteur', 'label' => 'Souscripteur', 'type' => 'text', 'required' => true],
                        ['key' => 'assureNomPrenom', 'label' => 'Nom et prénom', 'type' => 'text', 'required' => true, 'source' => 'patient.fullName'],
                        ['key' => 'coverageRate', 'label' => 'Taux de couverture', 'type' => 'number', 'required' => true],
                    ],
                ],
            ],
            [
                'code' => self::CODE_SAHAM,
                'nom' => 'SAHAM',
                'logoPath' => '/assurances/saham-logo.png',
                'website' => null,
                'email' => null,
                'formSchema' => [
                    'fields' => [
                        ['key' => 'assureNomPrenoms', 'label' => 'Assuré - Nom et prénoms', 'type' => 'text', 'required' => true],
                        ['key' => 'assureNumero', 'label' => 'Assuré - N°', 'type' => 'text', 'required' => true],
                        ['key' => 'beneficiaireNomPrenoms', 'label' => 'Bénéficiaire - Nom et prénoms', 'type' => 'text', 'required' => true, 'source' => 'patient.fullName'],
                        ['key' => 'beneficiaireMatricule', 'label' => 'Bénéficiaire - Matricule', 'type' => 'text', 'required' => true],
                        ['key' => 'coverageRate', 'label' => 'Taux de couverture', 'type' => 'number', 'required' => true],
                    ],
                ],
            ],
            [
                'code' => self::CODE_MSH,
                'nom' => 'MSH',
                'logoPath' => '/assurances/msh-logo.png',
                'website' => 'https://www.msh-intl.com',
                'email' => 'providers.africa@msh-intl.com',
                'formSchema' => [
                    'fields' => [
                        ['key' => 'identifiant', 'label' => 'Identifiant', 'type' => 'text', 'required' => true],
                        ['key' => 'nomPrenoms', 'label' => 'Nom et prénoms', 'type' => 'text', 'required' => true, 'source' => 'patient.fullName'],
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
            $isNew = $assurance->getId() === null;

            $assurance
                ->setCode($code)
                ->setLogoPath(isset($definition['logoPath']) ? (string) $definition['logoPath'] : null)
                ->setFormSchema(is_array($definition['formSchema'] ?? null) ? $definition['formSchema'] : []);

            if ($isNew) {
                $assurance
                    ->setNom((string) ($definition['nom'] ?? $code))
                    ->setWebsite(isset($definition['website']) ? (string) $definition['website'] : null)
                    ->setEmail(isset($definition['email']) ? (string) $definition['email'] : null)
                    ->setActif(false);
            }

            $em->persist($assurance);
            $result[] = $assurance;
        }

        $em->flush();

        return $result;
    }
}
