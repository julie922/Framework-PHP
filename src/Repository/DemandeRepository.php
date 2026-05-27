<?php

namespace App\Repository;

use App\Entity\Demande;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Demande>
 */
class DemandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Demande::class);
    }

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.demandeur = :user')
            ->setParameter('user', $user)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findWithFilters(?User $user, array $filters): array
    {
        $qb = $this->createQueryBuilder('d');

        if (!empty($filters['mine']) && $user) {
            $qb->andWhere('d.demandeur = :user')->setParameter('user', $user);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('d.status = :status')->setParameter('status', $filters['status']);
        }

        if (!empty($filters['category'])) {
            $qb->andWhere('d.category = :category')->setParameter('category', $filters['category']);
        }

        return $qb->orderBy('d.createdAt', 'DESC')->getQuery()->getResult();
    }
}
