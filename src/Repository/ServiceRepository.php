<?php

namespace App\Repository;

use App\Entity\Service;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Service>
 */
class ServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Service::class);
    }

    public function findWithFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.prestataire', 'p')
            ->addSelect('p');

        if (!empty($filters['status'])) {
            $qb->andWhere('s.status = :status')->setParameter('status', $filters['status']);
        } else {
            $qb->andWhere('s.status != :archived')->setParameter('archived', Service::STATUS_ARCHIVED);
        }

        if (!empty($filters['category'])) {
            $qb->andWhere('s.category = :category')->setParameter('category', $filters['category']);
        }

        if (!empty($filters['search'])) {
            $qb->andWhere('s.title LIKE :search OR s.description LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['priceMax'])) {
            $qb->andWhere('s.price <= :priceMax')->setParameter('priceMax', (float) $filters['priceMax']);
        }

        if (!empty($filters['priceMin'])) {
            $qb->andWhere('s.price >= :priceMin')->setParameter('priceMin', (float) $filters['priceMin']);
        }

        return $qb->orderBy('s.createdAt', 'DESC')->getQuery()->getResult();
    }
}
