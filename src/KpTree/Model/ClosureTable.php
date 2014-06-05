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
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Insert;
use Zend\Db\Sql\Predicate\Predicate;
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
        // TODO: Implement deleteChildNodeById() method.
    }

    public function deleteNodeById($idOrIds)
    {
        // TODO: Implement deleteNodeById() method.
    }

}