<?php
/**
 * Kittencup Module
 *
 * @date 2014 14-6-3 下午3:17
 * @copyright Copyright (c) 2014-2015 Kittencup. (http://www.kittencup.com)
 * @license   http://kittencup.com
 */
namespace KpTree\Model;

use KpTree\Exception\RuntimeException;
use Zend\Db\Adapter\Exception\InvalidQueryException;
use Zend\Db\Sql\Delete;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Predicate\Like;
use Zend\Db\Sql\Predicate\Predicate;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Where;
use Traversable;
use Zend\Stdlib\ArrayUtils;

/**
 * 路径枚举
 * Class PathEnumTable
 * @package KpTree\Model
 */
class PathEnumTable extends AbstractTreeTable
{
    /**
     * 表名
     * @var string
     */
    protected $table = 'pathEnum';

    /**
     * 路径数据库字段
     * @var string
     */
    protected $pathColumn = 'path';

    /**
     * 路径分隔符
     * @var string
     */
    protected $pathDelimiter = '/';


    /**
     * @param Array|\ArrayObject|Object $row
     * @param int $toId
     * @return int
     */
    public function addNode($row, $toId)
    {
        $row = $this->resultSetExtract($row);

        try {
            $this->getConnection()->beginTransaction();

            if ($this->insert($row) < 1) {
                throw new RuntimeException('node 新增失败');
            }

            $toRow = $this->getOneByColumn($toId, $this->idKey, [$this->pathColumn, $this->depthColumn]);

            $updateData = [
                $this->pathColumn => $toRow[$this->pathColumn] . $this->lastInsertValue . $this->pathDelimiter,
                $this->depthColumn => $toRow[$this->depthColumn] + 1
            ];

            if ($this->update($updateData, [$this->idKey => $this->lastInsertValue]) < 1) {
                throw new RuntimeException('node 更新失败');
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
        $fromRow = $this->getOneByColumn($fromId, $this->idKey, [$this->pathColumn, $this->depthColumn]);
        $toRow = $this->getOneByColumn($toId, $this->idKey, [$this->pathColumn, $this->depthColumn]);

        if (strpos($toRow[$this->pathColumn], $fromRow[$this->pathColumn]) !== false) {
            return -1;
        }
        $replacePath = substr($fromRow[$this->pathColumn], 0, strrpos($fromRow[$this->pathColumn], $this->pathDelimiter, -2) + 1);
        $updateDepth = $toRow[$this->depthColumn] - $fromRow[$this->depthColumn] + 1;
        return $this->update([
            $this->pathColumn => new Expression(
                    'replace(' . $this->quoteIdentifier($this->pathColumn) . ',\'' . $replacePath . '\',\'' . $toRow[$this->pathColumn] . '\')'
                ),
            $this->depthColumn => new Expression($this->quoteIdentifier($this->depthColumn) . '+' . $updateDepth)
        ], function (Where $where) use ($fromRow, $toRow) {
            $where->like($this->pathColumn, $fromRow[$this->pathColumn] . '%');
        });
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
        $row = $this->getOneByColumn($id, $this->idKey, [$this->pathColumn, $this->depthColumn]);

        return $this->select(function (Select $select) use ($row, $depth, $order, $columns) {


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

                if (!in_array($this->depthColumn, $columns)) {
                    $columns[] = $this->depthColumn;
                }

                $predicate = new Predicate();
                $predicate->greaterThanOrEqualTo($this->depthColumn, $row[$this->depthColumn] - $depth);
                $select->having($predicate);
            }

            $select->columns($columns);

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
        $row = $this->getOneByColumn($id, $this->idKey, [$this->pathColumn, $this->depthColumn]);

        return $this->select(function (Select $select) use ($row, $depth, $order, $columns) {

            $select->where([new Like($this->table . '.' . $this->pathColumn, $row[$this->pathColumn] . '%')]);

            if ($depth !== null) {

                if (!in_array($this->depthColumn, $columns)) {
                    $columns[] = $this->depthColumn;
                }

                $predicate = new Predicate();
                $predicate->lessThanOrEqualTo($this->depthColumn, $depth + $row[$this->depthColumn]);
                $select->having($predicate);
            }

            $select->columns($columns);
            $select->order([$this->pathColumn => $order]);

        });
    }

    /**
     * @param array|int|Traversable $idOrIds
     * @param bool $itself
     * @return int
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

        $row = $this->getOneByColumn($idOrIds, $this->idKey, [$this->pathColumn]);

        return $this->delete(function (Delete $delete) use ($idOrIds, $row, $itself) {
            $delete->where(function (Where $where) use ($idOrIds, $row, $itself) {
                $where->like($this->pathColumn, $row[$this->pathColumn] . '%');

                if (!$itself) {
                    $where->notEqualTo($this->idKey, $idOrIds);
                }
            });
        });
    }

    /**
     * @param array|int|Traversable $idOrIds
     * @return int
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

        $row = $this->getOneByColumn($idOrIds, $this->idKey, [$this->pathColumn, $this->depthColumn]);

        try {
            $this->getConnection()->beginTransaction();

            if ($this->delete([$this->idKey => $idOrIds]) < 1) {
                throw new RuntimeException('node 删除失败');
            }

            $replacePath = substr($row[$this->pathColumn], 0, strrpos($row[$this->pathColumn], $this->pathDelimiter, -2) + 1);

            $affectedRows = $this->update([
                $this->pathColumn => new Expression(
                        'replace(' . $this->quoteIdentifier($this->pathColumn) . ',\'' . $row[$this->pathColumn] . '\',\'' . $replacePath . '\')'
                    ),
                $this->depthColumn => new Expression($this->quoteIdentifier($this->depthColumn) . '- 1')
            ], function (Where $where) use ($row) {
                $where->like($this->pathColumn, $row[$this->pathColumn] . '%');
            });

            $this->getConnection()->commit();
        } catch (RuntimeException $e) {
            $this->getConnection()->rollback();
        } catch (InvalidQueryException $e) {
            $this->getConnection()->rollback();
        }

        return $affectedRows;

    }

}