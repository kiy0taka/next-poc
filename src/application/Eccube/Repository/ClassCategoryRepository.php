<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eccube\Repository;

use Doctrine\DBAL\Exception\DriverException;
use Eccube\ORM\Exception\ForeignKeyConstraintViolationException;
use Doctrine\Persistence\ManagerRegistry as RegistryInterface;
use Eccube\Entity\ClassCategory;

/**
 * ClasscategoryRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ClassCategoryRepository extends AbstractRepository
{
    /**
     * ClassCategoryRepository constructor.
     *
     * @param RegistryInterface $registry
     */
    public function __construct(
        RegistryInterface $registry
    ) {
        parent::__construct($registry, ClassCategory::class);
    }

    /**
     * 規格カテゴリの一覧を取得します.
     *
     * @param \Eccube\Entity\ClassName $ClassName 検索対象の規格名オブジェクト. 指定しない場合は、すべての規格を対象に取得します.
     *
     * @return array 規格カテゴリの配列
     */
    public function getList(\Eccube\Entity\ClassName $ClassName = null)
    {
        $qb = $this->createQueryBuilder('cc')
            ->orderBy('cc.sort_no', 'DESC'); // TODO ClassName ごとにソートした方が良いかも
        if ($ClassName) {
            $qb->where('cc.ClassName = :ClassName')->setParameter('ClassName', $ClassName);
        }
        $ClassCategories = $qb->getQuery()
            ->getResult();

        return $ClassCategories;
    }

    /**
     * 規格カテゴリを登録します.
     *
     * @param $ClassCategory
     */
    public function save($ClassCategory)
    {
        if (!$ClassCategory->getId()) {
            $ClassName = $ClassCategory->getClassName();
            $sortNo = $this->createQueryBuilder('cc')
                ->select('COALESCE(MAX(cc.sort_no), 0)')
                ->where('cc.ClassName = :ClassName')
                ->setParameter('ClassName', $ClassName)
                ->getQuery()
                ->getSingleScalarResult();

            $ClassCategory->setSortNo($sortNo + 1);
            $ClassCategory->setVisible(true);
        }

        $em = $this->getEntityManager();
        $em->persist($ClassCategory);
        $em->flush();
    }

    /**
     * 規格カテゴリを削除する.
     *
     * @param ClassCategory $ClassCategory
     *
     * @throws ForeignKeyConstraintViolationException 外部キー制約違反の場合
     * @throws DriverException SQLiteの場合, 外部キー制約違反が発生すると, DriverExceptionをthrowします.
     */
    public function delete($ClassCategory)
    {
        $this->createQueryBuilder('cc')
            ->update()
            ->set('cc.sort_no', 'cc.sort_no - 1')
            ->where('cc.sort_no > :sort_no AND cc.ClassName = :ClassName')
            ->setParameter('sort_no', $ClassCategory->getSortNo())
            ->setParameter('ClassName', $ClassCategory->getClassName())
            ->getQuery()
            ->execute();

        $em = $this->getEntityManager();
        $em->remove($ClassCategory);
        $em->flush();
    }

    /**
     * 規格カテゴリの表示/非表示を切り替える.
     *
     * @param $ClassCategory
     */
    public function toggleVisibility($ClassCategory)
    {
        if ($ClassCategory->isVisible()) {
            $ClassCategory->setVisible(false);
        } else {
            $ClassCategory->setVisible(true);
        }

        $em = $this->getEntityManager();
        $em->persist($ClassCategory);
        $em->flush();
    }
}
