<?php
/**
 * Kittencup Module
 *
 * @date 2014 14-6-3 下午3:17
 * @copyright Copyright (c) 2014-2015 Kittencup. (http://www.kittencup.com)
 * @license   http://kittencup.com
 */
namespace KpTree\Model;

use Zend\Db\Sql\Delete;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Predicate\Like;
use Zend\Db\Sql\Predicate\Predicate;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Where;
use KpTree\Exception\InvalidArgumentException;
use Traversable;
use Zend\Stdlib\ArrayUtils;

class PathEnumTable extends AbstractTreeTable
{

    protected $table = 'pathEnum';
    protected $pathColumn = 'path';
    protected $pathDelimiter = '/';
    protected $depthColumn = 'depth';

    public function add($row, $toId)
    {
        $row = $this->resultSetExtract($row);

        if (!is_array($row)) {
            throw new InvalidArgumentException('$row 必须是数组或者是数据实体对象');
        }

        if ($this->insert($row) > 0) {

            $toRow = $this->getOneByColumn($toId, $this->idKey, [$this->pathColumn, $this->depthColumn]);

            $updateData = [
                $this->pathColumn => $toRow[$this->pathColumn] . $this->lastInsertValue . $this->pathDelimiter,
                $this->depthColumn => $toRow[$this->depthColumn] + 1
            ];

            return $this->update($updateData, [$this->idKey => $this->lastInsertValue]);
        }

    }

    public function move($fromId, $toId)
    {
        $fromRow = $this->getOneByColumn($fromId, $this->idKey);
        $toRow = $this->getOneByColumn($toId, $this->idKey);
        if (strpos($toRow[$this->pathColumn], $fromRow[$this->pathColumn]) !== false) {
            return;
        }
        $replacePath = substr($fromRow[$this->pathColumn], 0, strrpos($fromRow[$this->pathColumn], $this->pathDelimiter, -2) + 1);
        $updateDepth = $toRow[$this->depthColumn] - $fromRow[$this->depthColumn] + 1;
        $this->update([
            $this->pathColumn => new Expression(
                    'replace(' . $this->quoteIdentifier($this->pathColumn) . ',\'' . $replacePath . '\',\'' . $toRow[$this->pathColumn] . '\')'
                ),
            $this->depthColumn => new Expression($this->quoteIdentifier($this->depthColumn) . '+' . $updateDepth)
        ], function (Where $where) use ($fromRow, $toRow) {
            $where->like($this->pathColumn, $fromRow[$this->pathColumn] . '%');
        });
    }

    public function getParentById($id, $depth = null, $order = 'ASC')
    {
        $row = $this->getOneByColumn($id, $this->idKey);

        return $this->select(function (Select $select) use ($row, $depth, $order) {

            $select->where(function (Where $where) use ($row) {

                $path = $row[$this->pathColumn];

                $paths = [$path];

                while (substr_count($path, $this->pathDelimiter) > 1) {
                    $paths[] = $path = substr($path, 0, strrpos($path, $this->pathDelimiter, -2) + 1);
                }
                $where->in($this->pathColumn, $paths);


            });

            $select->order([$this->pathColumn => $order]);

            if ($depth !== null) {
                $predicate = new Predicate();
                $predicate->greaterThanOrEqualTo($this->depthColumn, $row[$this->depthColumn] - $depth);
                $select->having($predicate);
            }

        })->toArray();
    }

    public function getChildById($id, $depth = null, $order = 'ASC')
    {

        $row = $this->getOneByColumn($id, $this->idKey);

        return $this->select(function (Select $select) use ($row, $depth, $order) {

            $select->where([new Like($this->table . '.' . $this->pathColumn, $row[$this->pathColumn] . '%')]);

            if ($depth !== null) {
                $predicate = new Predicate();
                $predicate->lessThanOrEqualTo($this->depthColumn, $depth + $row[$this->depthColumn]);
                $select->having($predicate);
            }

            $select->order([$this->pathColumn => $order]);

        })->toArray();
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


        $row = $this->getOneByColumn($idOrIds, $this->idKey);

        return $this->delete(function (Delete $delete) use ($row, $itself) {

            $delete->where(function (Where $where) use ($row, $itself) {
                $where->like($this->pathColumn, $row[$this->pathColumn] . '%');

                if (!$itself) {
                    $where->notEqualTo($this->idKey, $row[$this->idKey]);
                }
            });
        });
    }

    public function deleteById($idOrIds)
    {
        if ($idOrIds instanceof Traversable) {
            $idOrIds = ArrayUtils::iteratorToArray($idOrIds);
        }

        if (is_array($idOrIds)) {
            foreach ($idOrIds as $id) {
                $this->deleteChildById($id);
            }
            return;
        }

        $row = $this->getOneByColumn($idOrIds, $this->idKey);

    }

}