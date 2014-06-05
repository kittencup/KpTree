<?php
/**
 * Kittencup Module
 *
 * @date 2014 14-6-4 下午10:11
 * @copyright Copyright (c) 2014-2015 Kittencup. (http://www.kittencup.com)
 * @license   http://kittencup.com
 */

namespace KpTree\Model;

use KpTree\Exception\RuntimeException;
use Zend\Db\Adapter\Exception\InvalidQueryException;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Delete;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Insert;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Where;
use Traversable;
use Zend\Stdlib\ArrayUtils;

/**
 * Class ClosureTable
 * @package KpTree\Model
 */
class ClosureTable extends AbstractTreeTable
{

    /**
     * 表名
     * @var string
     */
    protected $table = 'closure';

    /**
     * 存储引用关系的表名
     * @var string
     */
    protected $pathsTable = 'closurePaths';

    /**
     * 祖先字段名
     * @var string
     */
    protected $ancestorColumn = 'ancestor';

    /**
     * 后辈字段名
     * @var string
     */
    protected $descendantColumn = 'descendant';


    /**
     * @param Array|\ArrayObject|Object $node
     * @param int $toId
     * @return bool|int
     */
    public function addNode($node, $toId)
    {
        $toNode = $this->getOneByColumn($toId, $this->idKey, [$this->depthColumn]);

        $node = $this->resultSetExtract($node);

        $node[$this->depthColumn] = $toNode[$this->depthColumn] + 1;

        try {
            $this->getConnection()->beginTransaction();

            if (!$this->insert($node)) {
                throw new RuntimeException('node 新增失败');
            }

            $nodeLastInserValue = $this->lastInsertValue;
            $lastInsertValueExpression = new Expression($nodeLastInserValue);
            $insert = new Insert($this->pathsTable);
            $select = new Select($this->pathsTable);
            $unionSelect = new Select();
            $unionSelect->columns([$lastInsertValueExpression, $lastInsertValueExpression]);
            $select->columns([$this->ancestorColumn, $lastInsertValueExpression])->where([$this->descendantColumn => $toId]);
            $select->combine($unionSelect);
            $insert->columns([$this->ancestorColumn, $this->descendantColumn]);
            $insert->values($select);
            $result = $this->executeSql($insert);
            if ($result->getAffectedRows() < 1) {
                throw new RuntimeException(sprintf($this->pathsTable) . '节点数据 添加失败');
            }
            $this->getConnection()->commit();
        } catch (RuntimeException $e) {
            $nodeLastInserValue = false;
            $this->getConnection()->rollback();
        } catch (InvalidQueryException $e) {
            $nodeLastInserValue = false;
            $this->getConnection()->rollback();
        }

        return $nodeLastInserValue;
    }


    /**
     * @param int $fromId
     * @param int $toId
     * @return int
     */
    public function moveNode($fromId, $toId)
    {
        $fromNode = $this->getOneByColumn($fromId, $this->idKey, [$this->depthColumn]);
        $toNode = $this->getOneByColumn($toId, $this->idKey, [$this->depthColumn]);

        /**
         * @todo Mysql 不支持在一个表里先查询在删除的操作，在这里先查询出内容来
         */
        $descendantSelect = new Select($this->pathsTable);
        $descendantSelect->columns([$this->descendantColumn])->where([$this->ancestorColumn => $fromId]);
        $result = $this->executeSql($descendantSelect);
        $descendantInList = $this->getInList($result, $this->descendantColumn);

        // 不允许父移到子
        if (in_array($toId, $descendantInList)) {
            return -1;
        }

        $ancestorSelect = new Select($this->pathsTable);
        $ancestorSelect->columns([$this->ancestorColumn])->where(function (Where $where) use ($fromId) {
            $where->equalTo($this->descendantColumn, $fromId);
            $where->notEqualTo($this->ancestorColumn, new Expression($this->descendantColumn));
        });
        $result = $this->executeSql($ancestorSelect);
        $ancestorInList = $this->getInList($result, $this->ancestorColumn);


        try {
            $this->getConnection()->beginTransaction();

            // 删除当前节点及子节点与当前节点父节点之间的引用(保留当前节点和自身的引用及节点与子元素节点的引用)
            $delete = new Delete($this->pathsTable);
            $delete->where(function (Where $where) use ($descendantInList, $ancestorInList) {
                $where->in($this->descendantColumn, $descendantInList);
                $where->in($this->ancestorColumn, $ancestorInList);
            });
            $result = $this->executeSql($delete);

            // 节点肯定有父节点，所以这里的影响条数一定大于0
            if ($result->getAffectedRows() < 1) {
                throw new RuntimeException($this->pathsTable . '节点数据 删除失败');
            }

            /**
             * @todo Zend Framework 2 不支持 CROSS JOIN
             */
            $sql = sprintf(
                'INSERT INTO %s (%s,%s) SELECT %s , %s FROM %s CROSS JOIN %s WHERE %s = %d AND %s = %d',
                $this->quoteIdentifier($this->pathsTable),
                $this->quoteIdentifier($this->ancestorColumn),
                $this->quoteIdentifier($this->descendantColumn),
                $this->quoteIdentifier('p.' . $this->ancestorColumn),
                $this->quoteIdentifier('s.' . $this->descendantColumn),
                $this->quoteIdentifier($this->pathsTable) . ' AS ' . $this->quoteIdentifier('p'),
                $this->quoteIdentifier($this->pathsTable) . ' AS ' . $this->quoteIdentifier('s'),
                $this->quoteIdentifier('p.' . $this->descendantColumn),
                $toId,
                $this->quoteIdentifier('s.' . $this->ancestorColumn),
                $fromId
            );

            // 调试打印
            if (static::$openDebug) {
                echo $sql, '<br>';
            }

            /* @var \Zend\Db\Adapter\Driver\Pdo\Result $result */
            $result = $this->getAdapter()->query($sql)->execute();

            $affectedRows = $result->getAffectedRows();
            if ($affectedRows < 1) {
                throw new RuntimeException($this->pathsTable . '节点数据 删除失败');
            }

            // 更新depth
            $this->update([
                $this->depthColumn => new Expression($this->quoteIdentifier($this->depthColumn) . '+' . ($toNode[$this->depthColumn] - $fromNode[$this->depthColumn] + 1))
            ], function (Where $where) use ($descendantInList) {
                $where->in($this->idKey, $descendantInList);
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


    /**
     * @param int $id
     * @param null $depth
     * @param string $order
     * @param array $columns
     * @return ResultSet
     */
    public function getParentNodeById($id, $depth = null, $order = 'ASC', $columns = ['*'])
    {
        $node = $this->getOneByColumn($id, $this->idKey, [$this->depthColumn]);

        return $this->select(function (Select $select) use ($id, $depth, $order, $columns, $node) {
            $select->columns($columns);
            $select->join(
                ['t2' => $this->pathsTable],
                $this->table . '.' . $this->idKey . '=' . 't2.' . $this->ancestorColumn,
                []
            );

            $select->where(['t2.' . $this->descendantColumn => $id]);

            if ($depth !== null) {

                $select->where(function (Where $where) use ($node, $depth) {
                    $where->greaterThanOrEqualTo($this->depthColumn, $node[$this->depthColumn] - $depth);
                });
            }

            $select->order([$this->depthColumn => $order]);
        });
    }


    /**
     * @param int $id
     * @param null $depth
     * @param string $order
     * @param array $columns
     * @return ResultSet
     */
    public function getChildNodeById($id, $depth = null, $order = 'ASC', $columns = ['*'])
    {

        $node = $this->getOneByColumn($id, $this->idKey, [$this->depthColumn]);

        return $this->select(function (Select $select) use ($id, $depth, $order, $columns, $node) {
            $select->columns($columns);
            $select->join(
                ['t2' => $this->pathsTable],
                $this->table . '.' . $this->idKey . '=' . 't2.' . $this->descendantColumn,
                []
            );
            $select->join(
                ['t3' => $this->pathsTable],
                't3.' . $this->descendantColumn . '=' . 't2.' . $this->descendantColumn,
                ['breadcrumbs' => new Expression("GROUP_CONCAT(t3." . $this->ancestorColumn . " ORDER BY " . 't4.' . $this->depthColumn . " SEPARATOR ',' )")]
            );
            $select->join(
                ['t4' => $this->table],
                't4.' . $this->idKey . '=' . 't3.' . $this->ancestorColumn,
                []
            );
            $select->where(['t2.' . $this->ancestorColumn => $id]);

            if ($depth !== null) {

                $select->where(function (Where $where) use ($node, $depth) {
                    $where->lessThanOrEqualTo($this->depthColumn, $node[$this->depthColumn] + $depth);
                });
            }

            $select->group($this->table . '.' . $this->idKey);
            $select->order(['breadcrumbs' => $order]);
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
                $this->{__FUNCTION__}($id);
            }
            return;
        }

        /**
         * @todo Mysql 不支持在一个表里先查询在删除的操作，在这里先查询出内容来
         */
        $select = new Select($this->pathsTable);
        $select->columns([$this->descendantColumn])->where([$this->ancestorColumn => $idOrIds]);

        if (!$itself) {
            $select->where(function (Where $where) use ($idOrIds) {
                $where->notEqualTo($this->descendantColumn, $idOrIds);
            });
        }

        $result = $this->executeSql($select);

        $inList = $this->getInList($result, $this->descendantColumn);

        try {
            $this->getConnection()->beginTransaction();

            $affectedRows = $this->delete(function (Delete $delete) use ($inList) {
                $delete->where(function (Where $where) use ($inList) {
                    $where->in($this->idKey, $inList);
                });
            });

            if ($affectedRows < 1) {
                throw new RuntimeException('Node 删除失败');
            }

            $delete = new Delete($this->pathsTable);
            $delete->where(function (Where $where) use ($inList) {
                $where->in($this->descendantColumn, $inList);
            });

            $result = $this->executeSql($delete);

            if ($result->getAffectedRows() < 1) {
                throw new RuntimeException($this->pathsTable . '节点数据 删除失败');
            }
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

    /**
     * @param array|int|\Traversable $idOrIds
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

        $select = new Select($this->pathsTable);
        $select->columns([$this->descendantColumn])->where([$this->ancestorColumn => $idOrIds]);
        $select->where(function (Where $where) use ($idOrIds) {
            $where->notEqualTo($this->descendantColumn, $idOrIds);
        });

        $result = $this->executeSql($select);

        $inList = $this->getInList($result, $this->descendantColumn);

        try {
            $this->getConnection()->beginTransaction();

            $affectedRows = $this->delete([$this->idKey => $idOrIds]);
            if ($affectedRows < 1) {
                throw new RuntimeException('节点 删除失败');
            }

            $delete = new Delete($this->pathsTable);
            $delete->where(function (Where $where) use ($idOrIds) {
                $where->equalTo($this->descendantColumn, $idOrIds);
                $where->or;
                $where->equalTo($this->ancestorColumn, $idOrIds);
            });

            $result = $this->executeSql($delete);

            if ($result->getAffectedRows() < 1) {
                throw new RuntimeException($this->pathsTable . '节点数据 删除失败');
            }

            if (!empty($inList)) {
                $this->update([$this->depthColumn => new Expression($this->quoteIdentifier($this->depthColumn) . '-1')], function (Where $where) use ($inList) {
                    $where->in($this->idKey, $inList);
                });
            }

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