<?php

namespace App\Service;

use App\Entity\Demande;
use App\Entity\User;
use App\Repository\PropositionRepository;
use Doctrine\ORM\EntityManagerInterface;

class DemandeService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PropositionRepository  $propositionRepository,
    ) {}

    public function create(User $demandeur, array $data): Demande
    {
        $demande = new Demande();
        $demande->setDemandeur($demandeur);
        $demande->setTitle($data['title']);
        $demande->setDescription($data['description']);
        $demande->setCategory($data['category']);
        $demande->setBudget((float) $data['budget']);

        $this->em->persist($demande);
        $this->em->flush();

        return $demande;
    }

    public function update(Demande $demande, array $data): Demande
    {
        // Les champs de contenu (titre, description…) ne sont modifiables que si la demande est ouverte.
        // Le changement de statut seul est toujours autorisé (géré par applyStatusTransition).
        $contentFields = array_intersect_key($data, array_flip(['title', 'description', 'category', 'budget']));
        if (!empty($contentFields) && $demande->getStatus() !== Demande::STATUS_OUVERTE) {
            throw new \LogicException('Seules les demandes ouvertes peuvent avoir leur contenu modifié.');
        }

        if (isset($data['title']))       $demande->setTitle($data['title']);
        if (isset($data['description'])) $demande->setDescription($data['description']);
        if (isset($data['category']))    $demande->setCategory($data['category']);
        if (isset($data['budget']))      $demande->setBudget((float) $data['budget']);
        if (isset($data['status']))      $this->applyStatusTransition($demande, $data['status']);

        $this->em->flush();
        return $demande;
    }

    public function delete(Demande $demande): void
    {
        $this->em->remove($demande);
        $this->em->flush();
    }

    private function applyStatusTransition(Demande $demande, string $newStatus): void
    {
        $allowed = [
            Demande::STATUS_OUVERTE  => [Demande::STATUS_FERMEE],
            Demande::STATUS_EN_COURS => [Demande::STATUS_VALIDEE, Demande::STATUS_OUVERTE],
            Demande::STATUS_VALIDEE  => [Demande::STATUS_FERMEE],
            Demande::STATUS_FERMEE   => [],
        ];

        if (!in_array($newStatus, $allowed[$demande->getStatus()] ?? [], true)) {
            throw new \LogicException(
                "Transition interdite: {$demande->getStatus()} → $newStatus"
            );
        }

        $demande->setStatus($newStatus);

        // Cascade : fermeture de la demande → toutes les propositions en attente sont annulées
        if ($newStatus === Demande::STATUS_FERMEE) {
            $this->propositionRepository->cancelAllPendingByDemande($demande);
        }
    }

    public function serialize(Demande $demande, bool $withPropositions = false): array
    {
        $data = [
            'id'          => $demande->getId(),
            'title'       => $demande->getTitle(),
            'description' => $demande->getDescription(),
            'category'    => $demande->getCategory(),
            'budget'      => $demande->getBudget(),
            'status'      => $demande->getStatus(),
            'demandeur'   => [
                'id'        => $demande->getDemandeur()->getId(),
                'firstName' => $demande->getDemandeur()->getFirstName(),
                'lastName'  => $demande->getDemandeur()->getLastName(),
            ],
            'createdAt' => $demande->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $demande->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];

        if ($withPropositions) {
            $data['propositions'] = $demande->getPropositions()->map(fn($p) => [
                'id'     => $p->getId(),
                'price'  => $p->getPrice(),
                'status' => $p->getStatus(),
                'prestataire' => [
                    'id'        => $p->getPrestataire()->getId(),
                    'firstName' => $p->getPrestataire()->getFirstName(),
                ],
            ])->toArray();
        }

        return $data;
    }
}
