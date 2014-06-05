<?php
/**
 * Kittencup Module
 *
 * @date 2014 14-6-2 下午4:19
 * @copyright Copyright (c) 2014-2015 Kittencup. (http://www.kittencup.com)
 * @license   http://kittencup.com
 */

namespace KpTree\Model;

use ArrayObject;
use KpTree\Exception\InvalidArgumentException;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Adapter\AdapterAwareInterface;
use Zend\Db\Sql\Select;
use Zend\Db\TableGateway\AbstractTableGateway;
use Zend\Db\TableGateway\Feature\EventFeature;
use Zend\Db\TableGateway\Feature\FeatureSet;
use Zend\EventManager\EventInterface;
use Zend\EventManager\EventManager;
use Zend\Db\Sql\SqlInterface;
use Traversable;
use Zend\Stdlib\ArrayUtils;
use Zend\Db\Adapter\Driver\ResultInterface;
/**
 * Class AbstractTreeTable
 * @package KpTree\Model
 */
abstract class AbstractTreeTable extends AbstractTableGateway implements AdapterAwareInterface,
    TreeTableInterface, TreeTableDebugInterface, TreeTableExpandInterface
{
    /**
     * @var bool
     */
    protected static $openDebug = false;

    /**
     * 表主键
     * @var string
     */
    protected $idKey = 'id';

    /**
     * 表 深度字段(必须有)
     * @var string
     */
    protected $depthColumn = 'depth';

    /**
     * @param $openDebug
     */
    public static function setOpenDebug($openDebug)
    {
        self::$openDebug = $openDebug;
    }

    /**
     * @return bool
     */
    public static function getOpenDebug()
    {
        return self::$openDebug;
    }


    /**
     * @param Adapter $adapter
     * @return null
     */
    public function setDbAdapter(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->initialize();
    }

    /**
     * Initialize
     *
     * @return null
     */
    public function initialize()
    {
        if (static::$openDebug) {
            $this->addDebugFeature();
        }

        parent::initialize();
    }

    /**
     * @param mixed $val
     * @param null $column
     * @param array $selectColumns
     * @return array|ArrayObject|null|object|Traversable
     */
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

    /**
     * @return \Zend\Db\Adapter\Driver\ConnectionInterface
     */
    public function getConnection()
    {
        return $this->getAdapter()->getDriver()->getConnection();
    }

    /**
     * 添加调试特性
     */
    public function addDebugFeature()
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

    /**
     * @param string $identifier
     * @return string
     */
    public function quoteIdentifier($identifier)
    {
        return $this->getAdapter()->getPlatform()->quoteIdentifierInFragment($identifier);
    }

    /**
     * @param array|ArrayObject|null|object|Traversable $row
     * @return array|ArrayObject|null|object|Traversable
     * @throws InvalidArgumentException
     */
    public function resultSetExtract($row)
    {
        if (!$row) {
            throw new InvalidArgumentException('$row节点不存在');
        }

        if ($row instanceof ArrayObject) {
            $row = $row->getArrayCopy();
        }
        if ($row instanceof Traversable) {
            $row = ArrayUtils::iteratorToArray($row);
        } elseif (is_object($row)) {
            $row = $this->resultSetPrototype->getHydrator()->extract($row);
        }

        if (!is_array($row)) {
            throw new InvalidArgumentException('$row 必须是数组或者是数据实体对象');
        }

        return $row;
    }

    /**
     * @param $executeSql
     * @return ResultInterface
     * @throws InvalidArgumentException
     */
    public function executeSql($executeSql)
    {
        if (!$executeSql instanceof SqlInterface) {
            throw new InvalidArgumentException('$executeSql 必须是 \Zend\Db\Sql\Ddl\SqlInterface 实例');
        }

        $class = get_class($executeSql);

        $executeAction = substr($class, strrpos($class, '\\') + 1);

        $this->featureSet->apply('pre' . $executeAction, array($executeSql));
        $statement = $this->sql->prepareStatementForSqlObject($executeSql);
        $result = $statement->execute();
        $this->featureSet->apply('post' . $executeAction, array($statement, $result, new ResultSet()));
        return $result;
    }

    /**
     * @param array|ArrayObject|Traversable $result
     * @param string $key
     * @return array
     */
    public function getInList($result, $key)
    {
        $result = $this->resultSetExtract($result);

        $inList = [];
        foreach ($result as $node) {
            $inList[] = $node[$key];
        }
        return $inList;
    }

}