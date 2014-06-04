<?php
/**
 * Kittencup Module
 *
 * @date 2014 14-6-2 下午4:19
 * @copyright Copyright (c) 2014-2015 Kittencup. (http://www.kittencup.com)
 * @license   http://kittencup.com
 */

namespace KpTree\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Adapter\AdapterAwareInterface;
use Zend\Db\Sql\Select;
use Zend\Db\TableGateway\AbstractTableGateway;
use Zend\Db\TableGateway\Feature\EventFeature;
use Zend\Db\TableGateway\Feature\FeatureSet;
use Zend\EventManager\EventInterface;
use Zend\EventManager\EventManager;
use KpTree\Exception\InvalidArgumentException;
use ArrayObject;

abstract class AbstractTreeTable extends AbstractTableGateway implements AdapterAwareInterface,
    TreeTableInterface
{
    protected static $openDebug = true;

    protected $idKey = 'id';

    protected $depthColumn = 'depth';

    public function getOneByColumn($val, $column = null, $selectColumns = ['*'])
    {
        if ($column === null) {
            $column = $this->idKey;
        }

        $row = $this->select(function (Select $select) use ($val, $column, $selectColumns) {
            $select->columns($selectColumns)->where([$column => $val]);
        })->current();

        return $this->resultSetExtract($row);

    }

    public function setDbAdapter(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->initialize();
    }

    public function initialize()
    {
        if (static::$openDebug) {
            $this->addDebugFeature();
        }

        parent::initialize();
    }

    protected function getConnection()
    {
        return $this->getAdapter()->getDriver()->getConnection();
    }

    protected function addDebugFeature()
    {

        if (!$this->featureSet instanceof FeatureSet) {
            $this->featureSet = new FeatureSet;
        }

        $eventManager = new EventManager();
        $eventManager->attach(['preSelect', 'preUpdate', 'preInsert', 'preDelete'], function (EventInterface $event) {
            $sqlKey = strtolower(str_replace('pre', '', $event->getName()));
            echo $event->getParam($sqlKey)->getSqlString($event->getTarget()->getAdapter()->getPlatform()), '<br>';
        });

        $this->featureSet->addFeature(new EventFeature($eventManager));
    }

    protected function quoteIdentifier($identifier)
    {
        return $this->getAdapter()->getPlatform()->quoteIdentifierInFragment($identifier);
    }

    protected function resultSetExtract($row)
    {
        if (!$row) {
            throw new InvalidArgumentException('$row节点不存在');
        }

        if ($row instanceof ArrayObject) {
            $row = $row->getArrayCopy();
        } elseif (is_object($row)) {
            $row = $this->resultSetPrototype->getHydrator()->extract($row);
        }

        if (!is_array($row)) {
            throw new InvalidArgumentException('$row 必须是数组或者是数据实体对象');
        }

        return $row;
    }

}