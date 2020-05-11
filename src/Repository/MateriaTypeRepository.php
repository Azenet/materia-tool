<?php

namespace App\Repository;

use App\Entity\MateriaType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method MateriaType|null find($id, $lockMode = null, $lockVersion = null)
 * @method MateriaType|null findOneBy(array $criteria, array $orderBy = null)
 * @method MateriaType[]    findAll()
 * @method MateriaType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MateriaTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MateriaType::class);
    }

    // /**
    //  * @return MateriaType[] Returns an array of MateriaType objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('m.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?MateriaType
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
