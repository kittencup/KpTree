<?php
/**
 * Kittencup Module
 *
 * @date 2014 14-6-3 下午3:17
 * @copyright Copyright (c) 2014-2015 Kittencup. (http://www.kittencup.com)
 * @license   http://kittencup.com
 */
namespace KpTree\Model;

use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Predicate\Like;
use Zend\Db\Sql\Predicate\Literal;
use Zend\Db\Sql\Predicate\Predicate;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Where;

class PathEnumTable extends AbstractTreeTable
{

    protected $table = 'pathEnum';
    protected $pathColumn = 'path';
    protected $pathDelimiter = '/';

    public function add($row, $toId)
    {

    }

    public function move($fromId, $toId)
    {

    }

    public function getParentById($id, $depth = null)
    {
        $row = $this->getOneByColumn($id, $this->idKey);

        return $this->select(function (Select $select) use ($row, $depth) {

            
            if ($depth !== null) {
                $predicate = new Predicate();
                $predicate->lessThanOrEqualTo('depth', $depth);
                $select->having($predicate);
            }

        })->toArray();
    }

    public function getChildById($id, $depth = null, $order = 'ASC')
    {

        $row = $this->getOneByColumn($id, $this->idKey);

        return $this->select(function (Select $select) use ($row, $depth, $order) {

            $select->columns([
                '*',
            ]);
            $select->where([new Like($this->table . '.' . $this->pathColumn, $row[$this->pathColumn] . '%')]);

            if ($depth !== null) {
                $predicate = new Predicate();
                $predicate->lessThanOrEqualTo('depth', $depth);
                $select->having($predicate);
            }

            $select->order([$this->pathColumn => $order]);

        })->toArray();
    }

    public function deleteChildById($idOrIds, $itself = true)
    {

    }

    public function deleteById($idOrIds)
    {

    }

}