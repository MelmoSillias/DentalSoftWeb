<?php

namespace App\Patient\Service;

use App\CareDelivery\Entity\Consultation;
use App\Patient\Entity\Appreciation;
use App\Patient\Entity\Patient;
use App\Patient\Repository\AppreciationRepository;
use Doctrine\ORM\EntityManagerInterface;

class AppreciationService
{
    public function __construct(
        private readonly AppreciationRepository $appreciationRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** @param array<string,mixed> $payload */
    public function createAnonymous(array $payload): Appreciation
    {
        $appreciation = $this->hydrateBaseFields(new Appreciation(), $payload, false);
        $appreciation
            ->setIsAnonymous(true)
            ->setPatient(null)
            ->setConsultation(null)
            ->setAuthorName($this->nullableTrimString($payload['authorName'] ?? $payload['nom'] ?? null))
            ->setAuthorEmail($this->nullableTrimString($payload['authorEmail'] ?? $payload['email'] ?? null));

        $this->entityManager->persist($appreciation);
        $this->entityManager->flush();

        return $appreciation;
    }

    /** @param array<string,mixed> $payload */
    public function createForPatient(Patient $patient, array $payload): Appreciation
    {
        $appreciation = $this->hydrateBaseFields(new Appreciation(), $payload);
        $appreciation
            ->setPatient($patient)
            ->setConsultation(null)
            ->setIsAnonymous((bool) ($payload['anonymous'] ?? false));

        if (!$appreciation->isAnonymous()) {
            $appreciation
                ->setAuthorName($this->nullableTrimString($payload['authorName'] ?? $payload['nom'] ?? null))
                ->setAuthorEmail($this->nullableTrimString($payload['authorEmail'] ?? $payload['email'] ?? null));
        }

        $this->entityManager->persist($appreciation);
        $this->entityManager->flush();

        return $appreciation;
    }

    /** @param array<string,mixed> $payload */
    public function createForConsultation(Patient $patient, Consultation $consultation, array $payload): Appreciation
    {
        if ($consultation->getPatient()?->getId() !== $patient->getId()) {
            throw new \InvalidArgumentException('Cette consultation n appartient pas au patient authentifie.');
        }

        if ($this->appreciationRepository->findOneByConsultation($consultation) instanceof Appreciation) {
            throw new \InvalidArgumentException('Une appreciation existe deja pour cette consultation.');
        }

        $appreciation = $this->hydrateBaseFields(new Appreciation(), $payload);
        $appreciation
            ->setPatient($patient)
            ->setConsultation($consultation)
            ->setIsAnonymous((bool) ($payload['anonymous'] ?? false));

        if (!$appreciation->isAnonymous()) {
            $appreciation
                ->setAuthorName($this->nullableTrimString($payload['authorName'] ?? $payload['nom'] ?? null))
                ->setAuthorEmail($this->nullableTrimString($payload['authorEmail'] ?? $payload['email'] ?? null));
        }

        $this->entityManager->persist($appreciation);
        $this->entityManager->flush();

        return $appreciation;
    }

    /** @return Appreciation[] */
    public function listByPatient(Patient $patient): array
    {
        return $this->appreciationRepository->findByPatient($patient);
    }

    /** @return Appreciation[] */
    public function listForAdmin(int $limit = 200): array
    {
        return $this->appreciationRepository->findLatestForAdmin($limit);
    }

    /** @return array{total:int,anonymous:int,published:int,averageRating:float} */
    public function getAdminStats(): array
    {
        return $this->appreciationRepository->getAdminStats();
    }

    /** @return Appreciation[] */
    public function listPublishedForPublic(int $limit = 50): array
    {
        return $this->appreciationRepository->findPublishedForPublic($limit);
    }

    /** @return array{total:int,averageRating:float} */
    public function getPublicStats(): array
    {
        return $this->appreciationRepository->getPublicStats();
    }

    public function findById(int $id): ?Appreciation
    {
        return $this->appreciationRepository->find($id);
    }

    public function setPublished(int $id, bool $isPublished): Appreciation
    {
        $appreciation = $this->findById($id);
        if (!$appreciation instanceof Appreciation) {
            throw new \InvalidArgumentException('Avis introuvable.');
        }

        $appreciation->setIsPublished($isPublished);
        $this->entityManager->flush();

        return $appreciation;
    }

    public function delete(int $id): void
    {
        $appreciation = $this->findById($id);
        if (!$appreciation instanceof Appreciation) {
            throw new \InvalidArgumentException('Avis introuvable.');
        }

        $this->entityManager->remove($appreciation);
        $this->entityManager->flush();
    }

    private function hydrateBaseFields(Appreciation $appreciation, array $payload, ?bool $defaultPublished = true): Appreciation
    {
        $rating = (int) ($payload['rating'] ?? $payload['note'] ?? 0);
        if ($rating < 1 || $rating > 5) {
            throw new \InvalidArgumentException('La note doit etre comprise entre 1 et 5.');
        }

        $comment = trim((string) ($payload['comment'] ?? $payload['commentaire'] ?? ''));
        if ($comment === '') {
            throw new \InvalidArgumentException('Le commentaire est requis.');
        }
        if (mb_strlen($comment) > 5000) {
            throw new \InvalidArgumentException('Le commentaire est trop long (5000 caracteres max).');
        }

        $published = array_key_exists('isPublished', $payload)
            ? (bool) $payload['isPublished']
            : ($defaultPublished ?? true);

        return $appreciation
            ->setRating($rating)
            ->setComment($comment)
            ->setIsPublished($published);
    }

    private function nullableTrimString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        return $normalized === '' ? null : $normalized;
    }
}
