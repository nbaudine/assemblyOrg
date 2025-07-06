<?php

namespace App\Repository;

use App\Entity\Ronde;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ronde>
 */
class RondeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ronde::class);
    }

    /**
     * Retourne toutes les rondes futures (>= maintenant), triées par date de début ASC.
     *
     * @return Ronde[]
     */
    public function findFuture(): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.start >= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('r.start', 'ASC')
            ->getQuery()
            ->getResult();
    }


    //    /**
    //     * @return Ronde[] Returns an array of Ronde objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('r.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Ronde
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
