<?php
/**
 * Kittencup Module
 *
 * @date 2014 14-6-3 下午12:51
 * @copyright Copyright (c) 2014-2015 Kittencup. (http://www.kittencup.com)
 * @license   http://kittencup.com
 */

namespace KpTree\Model;

interface TreeTableInterface
{

    public function add($row, $toId);

    public function move($fromId, $toId);

    public function getParentById($id, $depth = null);

    public function getChildById($id, $depth = null, $order = 'ASC');

    public function deleteChildById($idOrIds,$itself = true);

    public function deleteById($idOrIds);

}