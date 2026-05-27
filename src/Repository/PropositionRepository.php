<?php

namespace App\Repository;

use App\Entity\Demande;
use App\Entity\Proposition;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Proposition>
 */
class PropositionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Proposition::class);
    }

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.demande', 'd')
            ->addSelect('d')
            ->where('p.prestataire = :user OR d.demandeur = :user')
            ->setParameter('user', $user)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPendingByDemande(Demande $demande): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.demande = :demande')
            ->andWhere('p.status = :status')
            ->setParameter('demande', $demande)
            ->setParameter('status', Proposition::STATUS_EN_ATTENTE)
            ->getQuery()
            ->getResult();
    }

    public function refuseAllExcept(Demande $demande, Proposition $accepted): void
    {
        $this->createQueryBuilder('p')
            ->update()
            ->set('p.status', ':refused')
            ->where('p.demande = :demande')
            ->andWhere('p.id != :id')
            ->andWhere('p.status = :pending')
            ->setParameter('refused', Proposition::STATUS_REFUSEE)
            ->setParameter('demande', $demande)
            ->setParameter('id', $accepted->getId())
            ->setParameter('pending', Proposition::STATUS_EN_ATTENTE)
            ->getQuery()
            ->execute();
    }
}
