<?php

namespace App\Repository;

use App\Entity\Indisponibilite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Indisponibilite>
 */
class IndisponibiliteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Indisponibilite::class);
    }


// IndisponibiliteRepository.php
    public function findUsersIndisponiblesBetween(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $qb = $this->createQueryBuilder('i')
            ->select('IDENTITY(i.user)')
            ->where('i.start < :end')
            ->andWhere('i.end > :start')
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        return array_column($qb->getQuery()->getResult(), 1);
    }

    //    /**
    //     * @return Indisponibilite[] Returns an array of Indisponibilite objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('i')
    //            ->andWhere('i.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('i.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Indisponibilite
    //    {
    //        return $this->createQueryBuilder('i')
    //            ->andWhere('i.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
