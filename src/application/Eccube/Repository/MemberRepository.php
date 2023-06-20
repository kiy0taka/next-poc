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
use Eccube\Entity\Member;

/**
 * MemberRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class MemberRepository extends AbstractRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Member::class);
    }

    /**
     * 管理ユーザの表示順を一つ上げる.
     *
     * @param Member $Member
     *
     * @throws \Exception 更新対象のユーザより上位のユーザが存在しない場合.
     */
    public function up(Member $Member)
    {
        $sortNo = $Member->getSortNo();
        $Member2 = $this->findOneBy(['sort_no' => $sortNo + 1]);

        if (!$Member2) {
            throw new \Exception(sprintf('%s より上位の管理ユーザが存在しません.', $Member->getId()));
        }

        $Member->setSortNo($sortNo + 1);
        $Member2->setSortNo($sortNo);

        $em = $this->getEntityManager();
        $em->persist($Member);
        $em->persist($Member2);
        $em->flush();
    }

    /**
     * 管理ユーザの表示順を一つ下げる.
     *
     * @param Member $Member
     *
     * @throws \Exception 更新対象のユーザより下位のユーザが存在しない場合.
     */
    public function down(Member $Member)
    {
        $sortNo = $Member->getSortNo();
        $Member2 = $this->findOneBy(['sort_no' => $sortNo - 1]);

        if (!$Member2) {
            throw new \Exception(sprintf('%s より下位の管理ユーザが存在しません.', $Member->getId()));
        }

        $Member->setSortNo($sortNo - 1);
        $Member2->setSortNo($sortNo);

        $em = $this->getEntityManager();
        $em->persist($Member);
        $em->persist($Member2);
        $em->flush();
    }

    /**
     * 管理ユーザを登録します.
     *
     * @param Member $Member
     */
    public function save($Member)
    {
        if (!$Member->getId()) {
            $sortNo = $this->createQueryBuilder('m')
                ->select('COALESCE(MAX(m.sort_no), 0)')
                ->getQuery()
                ->getSingleScalarResult();
            $Member
                ->setSortNo($sortNo + 1);
        }

        $em = $this->getEntityManager();
        $em->persist($Member);
        $em->flush();
    }

    /**
     * 管理ユーザを削除します.
     *
     * @param Member $Member
     *
     * @throws ForeignKeyConstraintViolationException 外部キー制約違反の場合
     * @throws DriverException SQLiteの場合, 外部キー制約違反が発生すると, DriverExceptionをthrowします.
     */
    public function delete($Member)
    {
        $this->createQueryBuilder('m')
            ->update()
            ->set('m.sort_no', 'm.sort_no - 1')
            ->where('m.sort_no > :sort_no')
            ->setParameter('sort_no', $Member->getSortNo())
            ->getQuery()
            ->execute();

        $em = $this->getEntityManager();

        // ログインしたメンバーの外部参照制約を解除する
        // https://github.com/EC-CUBE/ec-cube/issues/5119
        $Member->setCreator(null);
        $em->flush();

        $em->remove($Member);
        $em->flush();
    }
}
