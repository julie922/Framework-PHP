<?php

namespace App\Service;

use App\Entity\Demande;
use App\Entity\Proposition;
use App\Entity\Service;
use App\Entity\User;
use App\Repository\PropositionRepository;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;

class PropositionService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PropositionRepository  $propositionRepository,
        private readonly ServiceRepository      $serviceRepository,
    ) {}

    public function create(User $prestataire, Demande $demande, array $data): Proposition
    {
        if ($demande->getStatus() !== Demande::STATUS_OUVERTE) {
            throw new \LogicException('Impossible de proposer sur une demande non ouverte.');
        }

        $proposition = new Proposition();
        $proposition->setPrestataire($prestataire);
        $proposition->setDemande($demande);
        $proposition->setPrice((float) $data['price']);
        $proposition->setMessage($data['message']);

        if (!empty($data['serviceId'])) {
            $service = $this->serviceRepository->find($data['serviceId']);
            if ($service && $service->getPrestataire()->getId() === $prestataire->getId()) {
                $proposition->setService($service);
            }
        }

        $this->em->persist($proposition);
        $this->em->flush();

        return $proposition;
    }

    public function updateStatus(Proposition $proposition, string $newStatus, User $currentUser): Proposition
    {
        $demande = $proposition->getDemande();

        if ($newStatus === Proposition::STATUS_ACCEPTEE) {
            if ($demande->getDemandeur()->getId() !== $currentUser->getId()) {
                throw new \LogicException('Seul le demandeur peut accepter une proposition.');
            }
            if ($proposition->getStatus() !== Proposition::STATUS_EN_ATTENTE) {
                throw new \LogicException('Seules les propositions en attente peuvent être acceptées.');
            }

            $proposition->setStatus(Proposition::STATUS_ACCEPTEE);
            $demande->setStatus(Demande::STATUS_EN_COURS);
            $this->propositionRepository->refuseAllExcept($demande, $proposition);

        } elseif ($newStatus === Proposition::STATUS_REFUSEE) {
            if ($demande->getDemandeur()->getId() !== $currentUser->getId()) {
                throw new \LogicException('Seul le demandeur peut refuser une proposition.');
            }
            $proposition->setStatus(Proposition::STATUS_REFUSEE);

        } elseif ($newStatus === Proposition::STATUS_ANNULEE) {
            if ($proposition->getPrestataire()->getId() !== $currentUser->getId()) {
                throw new \LogicException('Seul le prestataire peut annuler sa proposition.');
            }
            $proposition->setStatus(Proposition::STATUS_ANNULEE);

        } else {
            throw new \InvalidArgumentException("Transition de statut invalide: $newStatus");
        }

        $this->em->flush();
        return $proposition;
    }

    public function serialize(Proposition $proposition): array
    {
        $data = [
            'id'      => $proposition->getId(),
            'price'   => $proposition->getPrice(),
            'message' => $proposition->getMessage(),
            'status'  => $proposition->getStatus(),
            'demande' => [
                'id'    => $proposition->getDemande()->getId(),
                'title' => $proposition->getDemande()->getTitle(),
            ],
            'prestataire' => [
                'id'        => $proposition->getPrestataire()->getId(),
                'firstName' => $proposition->getPrestataire()->getFirstName(),
                'lastName'  => $proposition->getPrestataire()->getLastName(),
            ],
            'createdAt' => $proposition->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $proposition->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];

        if ($proposition->getService()) {
            $data['service'] = [
                'id'    => $proposition->getService()->getId(),
                'title' => $proposition->getService()->getTitle(),
            ];
        }

        return $data;
    }
}
