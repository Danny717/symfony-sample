<?php

namespace App\Repository;

use App\Entity\AdminLogger;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method AdminLogger|null find($id, $lockMode = null, $lockVersion = null)
 * @method AdminLogger|null findOneBy(array $criteria, array $orderBy = null)
 * @method AdminLogger[]    findAll()
 * @method AdminLogger[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AdminLoggerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminLogger::class);
    }

    /**
     * @param array $data
     * @return Query
     */
    public function getLogs(array $data): Query
    {
        $query = $this->createQueryBuilder('l');

        if (isset($data['order_by'])) {
            $query->addOrderBy('l.' . $data['order_by'], $data['direction'] === 1 ? 'ASC' : 'DESC');
        }else{
            $query->addOrderBy('l.createdAt', 'DESC');
        }

        return $query->getQuery();
    }

    /**
     * @param array $data
     * @return Query
     */
    public function searchLogs(array $data): Query
    {
        $query = $this->createQueryBuilder('l');

        if (!empty($data['key'])){
            $query->innerJoin('l.user', 'u')
                ->andWhere('l.createdAt LIKE :key')
                ->orWhere('u.email LIKE :key')
                ->setParameter(':key', '%' . $data['key'] . '%');
        }

        if (!empty($data['user_id'])){
            $query->andWhere('l.client = :clientId')->setParameter('clientId', $data['user_id']);
        }

        if (isset($data['order_by'])) {
            $query->addOrderBy('l.' . $data['order_by'], $data['direction'] === 1 ? 'ASC' : 'DESC');
        }else{
            $query->addOrderBy('l.createdAt', 'DESC');
        }

        return $query->getQuery();
    }

}
