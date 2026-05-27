<?php

namespace App\Service;

use App\Entity\Service;
use App\Entity\User;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;

class ServiceService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ServiceRepository      $serviceRepository,
    ) {}

    public function create(User $prestataire, array $data): Service
    {
        $service = new Service();
        $service->setPrestataire($prestataire);
        $service->setTitle($data['title']);
        $service->setDescription($data['description']);
        $service->setCategory($data['category']);
        $service->setPrice((float) $data['price']);

        if (!empty($data['status'])) {
            $service->setStatus($data['status']);
        }

        $this->em->persist($service);
        $this->em->flush();

        return $service;
    }

    public function update(Service $service, array $data): Service
    {
        if (isset($data['title']))       $service->setTitle($data['title']);
        if (isset($data['description'])) $service->setDescription($data['description']);
        if (isset($data['category']))    $service->setCategory($data['category']);
        if (isset($data['price']))       $service->setPrice((float) $data['price']);
        if (isset($data['status']))      $service->setStatus($data['status']);

        $this->em->flush();
        return $service;
    }

    public function delete(Service $service): void
    {
        $service->setStatus(Service::STATUS_ARCHIVED);
        $this->em->flush();
    }

    public function serialize(Service $service): array
    {
        return [
            'id'          => $service->getId(),
            'title'       => $service->getTitle(),
            'description' => $service->getDescription(),
            'category'    => $service->getCategory(),
            'price'       => $service->getPrice(),
            'status'      => $service->getStatus(),
            'prestataire' => [
                'id'        => $service->getPrestataire()->getId(),
                'firstName' => $service->getPrestataire()->getFirstName(),
                'lastName'  => $service->getPrestataire()->getLastName(),
            ],
            'createdAt' => $service->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $service->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
