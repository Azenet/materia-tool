<?php

namespace App\Repository;

use App\Entity\MateriaLoadout;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method MateriaLoadout|null find($id, $lockMode = null, $lockVersion = null)
 * @method MateriaLoadout|null findOneBy(array $criteria, array $orderBy = null)
 * @method MateriaLoadout[]    findAll()
 * @method MateriaLoadout[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MateriaLoadoutRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MateriaLoadout::class);
    }

    public function getLoadoutWithMaterias($id) {
    	return $this->createQueryBuilder('ml')
			->where('ml.id = :id')->setParameter('id', $id)
			->innerJoin('ml.items', 'mli')
			->addSelect('mli')
			->innerJoin('mli.materia', 'mlim')
			->addSelect('mlim')
			->getQuery()
			->getOneOrNullResult();
	}

    // /**
    //  * @return MateriaLoadout[] Returns an array of MateriaLoadout objects
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
    public function findOneBySomeField($value): ?MateriaLoadout
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
