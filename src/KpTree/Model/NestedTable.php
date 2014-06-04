<?php
/**
 * Kittencup Module
 *
 * @date 2014 14-6-1 下午5:43
 * @copyright Copyright (c) 2014-2015 Kittencup. (http://www.kittencup.com)
 * @license   http://kittencup.com
 */

namespace KpTree\Model;

use KpTree\Exception\InvalidArgumentException;
use Zend\Db\Sql\Delete;
use Zend\Db\Sql\Predicate\Between;
use Zend\Db\Sql\Predicate\Expression;
use Zend\Db\Sql\Predicate\Predicate;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Where;
use Traversable;
use Zend\Stdlib\ArrayUtils;

class NestedTable extends AbstractTreeTable
{
    protected $table = 'nested';
    protected $lColumn = 'l';
    protected $rColumn = 'r';
    protected $depthColumn = 'depth';


    public function add($row, $toId)
    {

        $row = $this->resultSetExtract($row);

        if (!is_array($row)) {
            throw new InvalidArgumentException('$row 必须是数组或者是数据实体对象');
        }

        $toRow = $this->getOneByColumn($toId, $this->idKey);

        $row[$this->lColumn] = $toRow[$this->rColumn];
        $row[$this->rColumn] = $toRow[$this->rColumn] + 1;
        $row[$this->depthColumn] = $toRow[$this->depthColumn] + 1;

        $this->update([
            $this->rColumn => new Expression($this->quoteIdentifier($this->rColumn) . '+2')
        ], function (Where $where) use ($toRow) {
            $where->greaterThanOrEqualTo($this->rColumn, $toRow[$this->rColumn]);
        });

        $this->update([
            $this->lColumn => new Expression($this->quoteIdentifier($this->lColumn) . '+2')
        ], function (Where $where) use ($toRow) {
            $where->greaterThan($this->lColumn, $toRow[$this->rColumn]);
        });

        return $this->insert($row);

    }


    public function move($fromId, $toId)
    {

        $fromRow = $this->getOneByColumn($fromId, $this->idKey);

        $toRow = $this->getOneByColumn($toId, $this->idKey);

        $differenceValue = $fromRow[$this->rColumn] - $fromRow[$this->lColumn];
        $depthValue = $toRow[$this->depthColumn] - $fromRow[$this->depthColumn] + 1;

        $child = $this->getChildById($fromId);

        $updateIds = [];
        foreach ($child as $row) {
            $row = $this->resultSetExtract($row);

            $updateIds[] = $row[$this->idKey];
        }

        if ($toRow[$this->rColumn] > $fromRow[$this->rColumn]) {

            $this->update([
                $this->lColumn => new Expression($this->quoteIdentifier($this->lColumn) . '-' . $differenceValue . '- 1')
            ], function (Where $where) use ($fromRow, $toRow) {
                $where->greaterThan($this->lColumn, $fromRow[$this->rColumn]);
            });

            $this->update([
                $this->rColumn => new Expression($this->quoteIdentifier($this->rColumn) . '-' . $differenceValue . '- 1')
            ], function (Where $where) use ($fromRow, $toRow) {
                $where->greaterThan($this->rColumn, $fromRow[$this->rColumn]);
                $where->lessThan($this->rColumn, $toRow[$this->rColumn]);
            });

            $modifyValue = $toRow[$this->rColumn] - $fromRow[$this->rColumn] - 1;

            $this->update([
                $this->rColumn => new Expression($this->quoteIdentifier($this->rColumn) . '+' . $modifyValue),
                $this->lColumn => new Expression($this->quoteIdentifier($this->lColumn) . '+' . $modifyValue),
                $this->depthColumn => new Expression($this->quoteIdentifier($this->depthColumn) . '+' . $depthValue)
            ], function (Where $where) use ($updateIds) {
                $where->in($this->idKey, $updateIds);
            });

        } else {

            $this->update([
                $this->lColumn => new Expression($this->quoteIdentifier($this->lColumn) . '+' . $differenceValue . '+ 1')
            ], function (Where $where) use ($fromRow, $toRow) {
                $where->greaterThan($this->lColumn, $toRow[$this->rColumn]);
                $where->lessThan($this->lColumn, $fromRow[$this->lColumn]);
            });


            $this->update([
                $this->rColumn => new Expression($this->quoteIdentifier($this->rColumn) . '+' . $differenceValue . '+ 1')
            ], function (Where $where) use ($fromRow, $toRow) {
                $where->greaterThanOrEqualTo($this->rColumn, $toRow[$this->rColumn]);
                $where->lessThan($this->rColumn, $fromRow[$this->lColumn]);
            });


            $modifyValue = $fromRow[$this->lColumn] - $toRow[$this->rColumn];

            $this->update([
                $this->rColumn => new Expression($this->quoteIdentifier($this->rColumn) . '-' . $modifyValue),
                $this->lColumn => new Expression($this->quoteIdentifier($this->lColumn) . '-' . $modifyValue),
                $this->depthColumn => new Expression($this->quoteIdentifier($this->depthColumn) . '+' . $depthValue)
            ], function (Where $where) use ($updateIds) {
                $where->in($this->idKey, $updateIds);
            });

        }


    }

    public function getParentById($id, $depth = null,$order = 'ASC')
    {

        return $this->select(function (Select $select) use ($id, $depth,$order) {

            $select->columns([$this->depthColumn])
                ->join(
                    ['t2' => $this->table],
                    new Between(
                        $this->table . '.' . $this->lColumn,
                        new Expression($this->quoteIdentifier('t2.' . $this->lColumn)),
                        new Expression($this->quoteIdentifier('t2.' . $this->rColumn))
                    )
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

    public function getChildById($id, $depth = null, $order = 'ASC')
    {
        return $this->select(function (Select $select) use ($id, $depth, $order) {

            $select->columns([$this->depthColumn])
                ->join(
                    ['t2' => $this->table],
                    new Between(
                        't2.' . $this->lColumn,
                        new Expression($this->quoteIdentifier($this->table . '.' . $this->lColumn)),
                        new Expression($this->quoteIdentifier($this->table . '.' . $this->rColumn))
                    )
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

    public function deleteChildById($idOrIds, $itself = true)
    {

        if ($idOrIds instanceof Traversable) {
            $idOrIds = ArrayUtils::iteratorToArray($idOrIds);
        }

        if (is_array($idOrIds)) {
            foreach ($idOrIds as $id) {
                $this->deleteChildById($id, $itself);
            }
            return;
        }

        $row = $this->getOneByColumn($idOrIds, $this->idKey, [$this->rColumn, $this->lColumn]);

        $affectedRows = $this->delete(function (Delete $delete) use ($row, $itself) {

            $lColumnFun = 'greaterThan';
            $rColumnFun = 'lessThan';

            if ($itself) {
                $lColumnFun = 'greaterThanOrEqualTo';
                $rColumnFun = 'lessThanOrEqualTo';
            }

            $delete->where->$lColumnFun($this->lColumn, $row[$this->lColumn]);
            $delete->where->$rColumnFun($this->rColumn, $row[$this->rColumn]);
        });

        return $affectedRows;
    }


    public function deleteById($idOrIds)
    {
        if ($idOrIds instanceof Traversable) {
            $idOrIds = ArrayUtils::iteratorToArray($idOrIds);
        }

        if (is_array($idOrIds)) {
            foreach ($idOrIds as $id) {
                $this->deleteById($id);
            }
            return;
        }

        $row = $this->getOneByColumn($idOrIds, $this->idKey, [$this->rColumn, $this->lColumn]);

        $affectedRows = $this->delete([$this->idKey => $idOrIds]);

        if ($affectedRows > 0) {
            $this->update([
                $this->depthColumn => new Expression($this->quoteIdentifier($this->depthColumn) . '-' . 1)
            ], function (Where $where) use ($row) {
                $where->greaterThan($this->lColumn, $row[$this->lColumn]);
                $where->lessThan($this->rColumn, $row[$this->rColumn]);
            });
        }

        return $affectedRows;
    }

}
