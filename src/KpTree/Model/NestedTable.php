<?php
/**
 * Kittencup Module
 *
 * @date 2014 14-6-1 下午5:43
 * @copyright Copyright (c) 2014-2015 Kittencup. (http://www.kittencup.com)
 * @license   http://kittencup.com
 */

namespace KpTree\Model;

use KpTree\Exception\RuntimeException;
use Zend\Db\Adapter\Exception\InvalidQueryException;
use Zend\Db\Sql\Delete;
use Zend\Db\Sql\Predicate\Between;
use Zend\Db\Sql\Predicate\Expression;
use Zend\Db\Sql\Predicate\Predicate;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Where;
use Traversable;
use Zend\Stdlib\ArrayUtils;

/**
 * 嵌套集
 * Class NestedTable
 * @package KpTree\Model
 */
class NestedTable extends AbstractTreeTable
{
    /**
     * 表名
     * @var string
     */
    protected $table = 'nested';

    /**
     * 左数据库字段
     * @var string
     */
    protected $lColumn = 'l';

    /**
     * 右数据库字段
     * @var string
     */
    protected $rColumn = 'r';

    /**
     * @param Array|\ArrayObject|Object $node
     * @param int $toId
     * @return int
     */
    public function addNode($node, $toId)
    {
        $node = $this->resultSetExtract($node);

        $toNode = $this->getOneByColumn($toId, $this->idKey, [$this->rColumn, $this->lColumn, $this->depthColumn]);

        $node[$this->lColumn] = $toNode[$this->rColumn];
        $node[$this->rColumn] = $toNode[$this->rColumn] + 1;
        $node[$this->depthColumn] = $toNode[$this->depthColumn] + 1;

        try {
            $this->getConnection()->beginTransaction();

            $this->update([
                $this->rColumn => new Expression($this->quoteIdentifier($this->rColumn) . '+2')
            ], function (Where $where) use ($toNode) {
                $where->greaterThanOrEqualTo($this->rColumn, $toNode[$this->rColumn]);
            });

            $this->update([
                $this->lColumn => new Expression($this->quoteIdentifier($this->lColumn) . '+2')
            ], function (Where $where) use ($toNode) {
                $where->greaterThan($this->lColumn, $toNode[$this->rColumn]);
            });

            if ($this->insert($node) < 1) {
                throw new RuntimeException('node 新增失败');
            }

            $this->getConnection()->commit();
        } catch (RuntimeException $e) {
            $this->getConnection()->rollback();
        } catch (InvalidQueryException $e) {
            $this->getConnection()->rollback();
        }

        return $this->lastInsertValue;
    }

    /**
     * @param int $fromId
     * @param int $toId
     * @return int
     */
    public function moveNode($fromId, $toId)
    {

        $fromNode = $this->getOneByColumn($fromId, $this->idKey, [$this->rColumn, $this->lColumn, $this->depthColumn]);
        $toNode = $this->getOneByColumn($toId, $this->idKey, [$this->rColumn, $this->lColumn, $this->depthColumn]);

        if ($fromNode[$this->lColumn] < $toNode[$this->rColumn] && $fromNode[$this->rColumn] > $toNode[$this->rColumn]) {
            return -1;
        }

        $differenceValue = $fromNode[$this->rColumn] - $fromNode[$this->lColumn];
        $depthValue = $toNode[$this->depthColumn] - $fromNode[$this->depthColumn] + 1;

        $child = $this->getChildNodeById($fromId, null, 'ASC', [$this->idKey]);

        $updateIds = [];
        foreach ($child as $node) {

            $node = $this->resultSetExtract($node);

            $updateIds[] = $node[$this->idKey];
        }

        try {

            $this->getConnection()->beginTransaction();

            if ($toNode[$this->rColumn] > $fromNode[$this->rColumn]) {

                $this->update([
                    $this->lColumn => new Expression($this->quoteIdentifier($this->lColumn) . '-' . $differenceValue . '- 1')
                ], function (Where $where) use ($fromNode, $toNode) {
                    $where->greaterThan($this->lColumn, $fromNode[$this->rColumn]);
                });

                $this->update([
                    $this->rColumn => new Expression($this->quoteIdentifier($this->rColumn) . '-' . $differenceValue . '- 1')
                ], function (Where $where) use ($fromNode, $toNode) {
                    $where->greaterThan($this->rColumn, $fromNode[$this->rColumn]);
                    $where->lessThan($this->rColumn, $toNode[$this->rColumn]);
                });

                $modifyValue = $toNode[$this->rColumn] - $fromNode[$this->rColumn] - 1;

                $affectedRows = $this->update([
                    $this->rColumn => new Expression($this->quoteIdentifier($this->rColumn) . '+' . $modifyValue),
                    $this->lColumn => new Expression($this->quoteIdentifier($this->lColumn) . '+' . $modifyValue),
                    $this->depthColumn => new Expression($this->quoteIdentifier($this->depthColumn) . '+' . $depthValue)
                ], function (Where $where) use ($updateIds) {
                    $where->in($this->idKey, $updateIds);
                });

            } else {

                $this->update([
                    $this->lColumn => new Expression($this->quoteIdentifier($this->lColumn) . '+' . $differenceValue . '+ 1')
                ], function (Where $where) use ($fromNode, $toNode) {
                    $where->greaterThan($this->lColumn, $toNode[$this->rColumn]);
                    $where->lessThan($this->lColumn, $fromNode[$this->lColumn]);
                });


                $this->update([
                    $this->rColumn => new Expression($this->quoteIdentifier($this->rColumn) . '+' . $differenceValue . '+ 1')
                ], function (Where $where) use ($fromNode, $toNode) {
                    $where->greaterThanOrEqualTo($this->rColumn, $toNode[$this->rColumn]);
                    $where->lessThan($this->rColumn, $fromNode[$this->lColumn]);
                });


                $modifyValue = $fromNode[$this->lColumn] - $toNode[$this->rColumn];

                $affectedRows = $this->update([
                    $this->rColumn => new Expression($this->quoteIdentifier($this->rColumn) . '-' . $modifyValue),
                    $this->lColumn => new Expression($this->quoteIdentifier($this->lColumn) . '-' . $modifyValue),
                    $this->depthColumn => new Expression($this->quoteIdentifier($this->depthColumn) . '+' . $depthValue)
                ], function (Where $where) use ($updateIds) {
                    $where->in($this->idKey, $updateIds);
                });

            }
            $this->getConnection()->commit();
        } catch (InvalidQueryException $e) {
            $affectedRows = 0;
            $this->getConnection()->rollback();
        }

        return $affectedRows;

    }


    /**
     * @param int $id
     * @param null $depth
     * @param string $order
     * @param array $columns
     * @return \Zend\Db\ResultSet\ResultSet
     */
    public function getParentNodeById($id, $depth = null, $order = 'ASC', $columns = ['*'])
    {
        return $this->select(function (Select $select) use ($id, $depth, $order, $columns) {

            if (!in_array($this->depthColumn, $columns)) {
                $columns[] = $this->depthColumn;
            }

            $select->columns([$this->depthColumn])
                ->join(
                    ['t2' => $this->table],
                    new Between(
                        $this->table . '.' . $this->lColumn,
                        new Expression($this->quoteIdentifier('t2.' . $this->lColumn)),
                        new Expression($this->quoteIdentifier('t2.' . $this->rColumn))
                    ),
                    $columns
                )->where(function (Where $where) use ($id) {
                    $where->equalTo($this->table . '.' . $this->idKey, $id);
                });

            if ($depth !== null) {

                $predicate = new Predicate();
                $predicate->greaterThanOrEqualTo(
                    't2.' . $this->depthColumn,
                    new Expression($this->quoteIdentifier($this->table . '.' . $this->depthColumn) . '-' . (int)$depth)
                );
                $select->having($predicate);
            };

            $select->order(['t2.' . $this->lColumn => $order]);
        });

    }

    /**
     * @param int $id
     * @param null $depth
     * @param string $order
     * @param array $columns
     * @return \Zend\Db\ResultSet\ResultSet
     */
    public function getChildNodeById($id, $depth = null, $order = 'ASC', $columns = ['*'])
    {

        return $this->select(function (Select $select) use ($id, $depth, $order, $columns) {

            if (!in_array($this->depthColumn, $columns)) {
                $columns[] = $this->depthColumn;
            }

            $select->columns([$this->depthColumn])
                ->join(
                    ['t2' => $this->table],
                    new Between(
                        't2.' . $this->lColumn,
                        new Expression($this->quoteIdentifier($this->table . '.' . $this->lColumn)),
                        new Expression($this->quoteIdentifier($this->table . '.' . $this->rColumn))
                    ),
                    $columns
                )->where(function (Where $where) use ($id) {
                    $where->equalTo($this->table . '.' . $this->idKey, $id);
                });

            if ($depth !== null) {

                $predicate = new Predicate();
                $predicate->lessThanOrEqualTo(
                    't2.' . $this->depthColumn,
                    new Expression($this->quoteIdentifier($this->table . '.' . $this->depthColumn) . '+' . (int)$depth)
                );
                $select->having($predicate);

            };

            $select->order(['t2.' . $this->lColumn => $order]);

        });

    }

    /**
     * @param array|int|Traversable $idOrIds
     * @param bool $itself
     * @return int | null
     */
    public function deleteChildNodeById($idOrIds, $itself = true)
    {

        if ($idOrIds instanceof Traversable) {
            $idOrIds = ArrayUtils::iteratorToArray($idOrIds);
        }

        if (is_array($idOrIds)) {
            foreach ($idOrIds as $id) {
                $this->{__FUNCTION__}($id, $itself);
            }
            return;
        }

        $node = $this->getOneByColumn($idOrIds, $this->idKey, [$this->rColumn, $this->lColumn]);

        $affectedRows = $this->delete(function (Delete $delete) use ($node, $itself) {

            $lColumnFun = 'greaterThan';
            $rColumnFun = 'lessThan';

            if ($itself) {
                $lColumnFun = 'greaterThanOrEqualTo';
                $rColumnFun = 'lessThanOrEqualTo';
            }

            $delete->where->$lColumnFun($this->lColumn, $node[$this->lColumn]);
            $delete->where->$rColumnFun($this->rColumn, $node[$this->rColumn]);
        });

        return $affectedRows;
    }

    /**
     * @param array|int|Traversable $idOrIds
     * @return  int | null
     */
    public function deleteNodeById($idOrIds)
    {
        if ($idOrIds instanceof Traversable) {
            $idOrIds = ArrayUtils::iteratorToArray($idOrIds);
        }

        if (is_array($idOrIds)) {
            foreach ($idOrIds as $id) {
                $this->{__FUNCTION__}($id);
            }
            return;
        }

        $node = $this->getOneByColumn($idOrIds, $this->idKey, [$this->rColumn, $this->lColumn]);

        try {
            $this->getConnection()->beginTransaction();

            $affectedRows = $this->delete([$this->idKey => $idOrIds]);

            if ($affectedRows < 1) {
                throw new RuntimeException('node 删除失败');
            }

            $this->update([
                $this->depthColumn => new Expression($this->quoteIdentifier($this->depthColumn) . '-' . 1)
            ], function (Where $where) use ($node) {
                $where->greaterThan($this->lColumn, $node[$this->lColumn]);
                $where->lessThan($this->rColumn, $node[$this->rColumn]);
            });

            $this->getConnection()->commit();
        } catch (RuntimeException $e) {
            $affectedRows = 0;
            $this->getConnection()->rollback();
        } catch (InvalidQueryException $e) {
            $affectedRows = 0;
            $this->getConnection()->rollback();
        }

        return $affectedRows;
    }

}
