<?php

namespace App\CareDelivery\Infrastructure\Persistence\Doctrine\Repository;

use App\CareDelivery\Domain\Model\ActeMedical as DomainActeMedical;
use App\CareDelivery\Domain\Model\Consultation;
use App\CareDelivery\Domain\Repository\ConsultationRepository;
use App\CareDelivery\Domain\ValueObject\ConsultationId;
use App\CareDelivery\Infrastructure\Persistence\Doctrine\Entity\ActeMedical as EntityActeMedical;
use App\CareDelivery\Infrastructure\Persistence\Doctrine\Entity\Consultation as EntityConsultation;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\Employe;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineConsultationRepository implements ConsultationRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(Consultation $consultation): void
    {
        $id = $consultation->getId();
        if ($id === null) {
            throw new \RuntimeException('Creating consultations via Domain repository is not supported in this strangler slice.');
        }

        $entity = $this->em->find(EntityConsultation::class, $id->toInt());
        if (!$entity instanceof EntityConsultation) {
            throw new \RuntimeException(sprintf('Consultation entity #%d not found for save.', $id->toInt()));
        }

        if (method_exists($entity, 'setStatut')) {
            $entity->setStatut($consultation->getStatus());
        }

        $this->syncMedecin($entity, $consultation->getMedecinId());
        $this->syncActes($entity, $consultation->getActes());

        $this->em->persist($entity);
        $this->em->flush();
    }

    public function findById(ConsultationId $id): ?Consultation
    {
        $entity = $this->em->find(EntityConsultation::class, $id->toInt());
        if (!$entity instanceof EntityConsultation) {
            return null;
        }

        $patient = $entity->getPatient();
        if ($patient === null || $patient->getId() === null) {
            return null;
        }

        $medecinId = $entity->getMedecin()?->getId();
        $ficheId = $entity->getFicheMedicale()?->getId();

        $actes = [];
        foreach ($entity->getActes() as $acte) {
            if (!$acte instanceof EntityActeMedical) {
                continue;
            }

            $type = trim((string) ($acte->getType() ?? ''));
            if ($type === '') {
                continue;
            }

            $actes[] = new DomainActeMedical(
                $type,
                $acte->getDent(),
                $acte->getDescription(),
                (float) ($acte->getPrix() ?? 0),
                (int) ($acte->getQuantite() ?? 1),
            );
        }

        return Consultation::reconstitute(
            ConsultationId::fromInt((int) $entity->getId()),
            (int) $patient->getId(),
            $entity->getStatut(),
            $medecinId !== null ? (int) $medecinId : null,
            $ficheId !== null ? (int) $ficheId : null,
            $actes,
        );
    }

    private function syncMedecin(EntityConsultation $entity, ?int $medecinId): void
    {
        if ($medecinId === null) {
            return;
        }

        $currentId = $entity->getMedecin()?->getId();
        if ($currentId !== null && (int) $currentId === $medecinId) {
            return;
        }

        // Only assign when currently unassigned to avoid breaking FK / ownership rules.
        if ($currentId !== null) {
            return;
        }

        $medecin = $this->em->find(Employe::class, $medecinId);
        if ($medecin instanceof Employe) {
            $entity->setMedecin($medecin);
        }
    }

    /**
     * @param list<DomainActeMedical> $actes
     */
    private function syncActes(EntityConsultation $entity, array $actes): void
    {
        foreach ($entity->getActes()->toArray() as $existing) {
            if ($existing instanceof EntityActeMedical) {
                $entity->removeActe($existing);
                $this->em->remove($existing);
            }
        }

        foreach ($actes as $acte) {
            $entityActe = new EntityActeMedical();
            $entityActe->setType($acte->getType())
                ->setDent($acte->getDent() ?? '')
                ->setDescription($acte->getDescription())
                ->setPrix($acte->getPrix())
                ->setQuantite($acte->getQuantite());
            $entity->addActe($entityActe);
            $this->em->persist($entityActe);
        }
    }
}
