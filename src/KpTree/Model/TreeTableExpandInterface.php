<?php
/**
 * Kittencup Module
 *
 * @date 2014 14-6-5 下午9:52
 * @copyright Copyright (c) 2014-2015 Kittencup. (http://www.kittencup.com)
 * @license   http://kittencup.com
 */

namespace KpTree\Model;

use Traversable;
use ArrayObject;
use Zend\Db\Adapter\Driver\ResultInterface;
use Zend\Db\Adapter\Driver\ConnectionInterface;
/**
 * Interface KpTreeTableInterface
 * @package KpTree\Model
 */
interface TreeTableExpandInterface
{
    /**
     * 根据条件获取一条数据
     * @param mixed $val
     * @param null|string $column
     * @param array $selectColumns
     * @return array
     */
    public function getOneByColumn($val, $column = null, $selectColumns = ['*']);

    /**
     * 获取Connection 方便事物处理
     * @return ConnectionInterface
     */
    public function getConnection();

    /**
     * 给表及字段加上符号'`'
     * @param string $identifier
     * @return string
     */
    public function quoteIdentifier($identifier);


    /**
     * 将结果集转换为数组
     * @param ArrayObject|null|array|object|Traversable $row
     * @return array
     */
    public function resultSetExtract($row);

    /**
     * 执行Select Insert Update Delete
     * @param $executeSql
     * @return ResultInterface
     */
    public function executeSql($executeSql);

    /**
     * 获取指定数据键的集合
     * @param ArrayObject|array|Traversable $result
     * @param string $key
     * @return array
     */
    public function getInList($result, $key);
}