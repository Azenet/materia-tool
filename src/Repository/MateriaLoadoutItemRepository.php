<?php

namespace App\Repository;

use App\Entity\MateriaLoadoutItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method MateriaLoadoutItem|null find($id, $lockMode = null, $lockVersion = null)
 * @method MateriaLoadoutItem|null findOneBy(array $criteria, array $orderBy = null)
 * @method MateriaLoadoutItem[]    findAll()
 * @method MateriaLoadoutItem[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MateriaLoadoutItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MateriaLoadoutItem::class);
    }

    // /**
    //  * @return MateriaLoadoutItem[] Returns an array of MateriaLoadoutItem objects
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
    public function findOneBySomeField($value): ?MateriaLoadoutItem
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
