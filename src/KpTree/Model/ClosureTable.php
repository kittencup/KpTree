<?php
/**
 * Kittencup Module
 *
 * @date 2014 14-6-4 下午10:11
 * @copyright Copyright (c) 2014-2015 Kittencup. (http://www.kittencup.com)
 * @license   http://kittencup.com
 */

namespace KpTree\Model;

class ClosureTable extends AbstractTreeTable
{

    protected $table = 'closure';

    protected $pathsTable = 'closurePaths';

    protected $ancestorColumn = 'ancestor';

    protected $descendantColumn = 'descendant';


    public function addNode($row, $toId)
    {
        // TODO: Implement addNode() method.
    }


    public function moveNode($fromId, $toId)
    {
        // TODO: Implement moveNode() method.
    }


    public function getParentNodeById($id, $depth = null, $order = 'ASC', $columns = ['*'])
    {
        // TODO: Implement getParentNodeById() method.
    }


    public function getChildNodeById($id, $depth = null, $order = 'ASC', $columns = ['*'])
    {
        // TODO: Implement getChildNodeById() method.
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