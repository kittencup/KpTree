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

class ClosureTable extends AbstractTreeTable
{

    protected $table = 'closure';

    protected $pathsTable = 'closurePaths';

    protected $ancestorColumn = 'ancestor';

    protected $descendantColumn = 'descendant';


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

            $insert->values($select);
            $this->featureSet->apply('preInsert', array($insert));
            $statement = $this->sql->prepareStatementForSqlObject($insert);
            $result = $statement->execute();
            $this->featureSet->apply('postInsert', array($statement, $result));
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


    public function moveNode($fromId, $toId)
    {
        // TODO: Implement moveNode() method.
    }


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
        })->toArray();
    }


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
                ['breadcrumbs' => new Expression("GROUP_CONCAT(t3." . $this->ancestorColumn . " SEPARATOR ',' )")]
            );
            $select->where(['t2.' . $this->ancestorColumn => $id]);

            if ($depth !== null) {

                $select->where(function (Where $where) use ($node, $depth) {
                    $where->lessThanOrEqualTo($this->depthColumn, $node[$this->depthColumn] + $depth);
                });
            }

            $select->group($this->table . '.' . $this->idKey);
            $select->order(['breadcrumbs' => $order]);
        })->toArray();


    }

    public function deleteChildNodeById($idOrIds, $itself = true)
    {
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

        $this->featureSet->apply('preSelect', array($select));
        $statement = $this->sql->prepareStatementForSqlObject($select);
        $result = $statement->execute();
        $this->featureSet->apply('postSelect', array($statement, $result, new ResultSet()));

        $ids = [];
        foreach ($result as $node) {
            $ids[] = $node[$this->descendantColumn];
        }

        try {
            $this->getConnection()->beginTransaction();

            $affectedRows = $this->delete(function (Delete $delete) use ($ids) {
                $delete->where(function (Where $where) use ($ids) {
                    $where->in($this->idKey, $ids);
                });
            });

            if ($affectedRows < 1) {
                throw new RuntimeException('Node 删除失败');
            }

            $delete = new Delete($this->pathsTable);
            $delete->where(function (Where $where) use ($ids) {
                $where->in($this->descendantColumn, $ids);
            });

            $this->featureSet->apply('preDelete', array($delete));
            $statement = $this->sql->prepareStatementForSqlObject($delete);
            $result = $statement->execute();
            $this->featureSet->apply('postDelete', array($statement, $result));
            if ($result->getAffectedRows() < 1) {
                throw new RuntimeException(sprintf($this->pathsTable) . '节点数据 删除失败');
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

    public function deleteNodeById($idOrIds)
    {
        $select = new Select($this->pathsTable);
        $select->columns([$this->descendantColumn])->where([$this->ancestorColumn => $idOrIds]);
        $select->where(function (Where $where) use ($idOrIds) {
            $where->notEqualTo($this->descendantColumn, $idOrIds);
        });

        $this->featureSet->apply('preSelect', array($select));
        $statement = $this->sql->prepareStatementForSqlObject($select);
        $result = $statement->execute();
        $this->featureSet->apply('postSelect', array($statement, $result, new ResultSet()));

        $ids = [];
        foreach ($result as $node) {
            $ids[] = $node[$this->descendantColumn];
        }

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
            $this->featureSet->apply('preDelete', array($delete));
            $statement = $this->sql->prepareStatementForSqlObject($delete);
            $result = $statement->execute();
            $this->featureSet->apply('postDelete', array($statement, $result));

            if ($result->getAffectedRows() < 1) {
                throw new RuntimeException($this->pathsTable . '节点数据 删除失败');
            }

            if (!empty($ids)) {
                $this->update([$this->depthColumn => new Expression($this->quoteIdentifier($this->depthColumn) . '-1')], function (Where $where) use ($ids) {
                    $where->in($this->idKey, $ids);
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