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
    /**
     * 添加一个节点
     *
     * <code>
     *      $table->addNode(['name'=>'kittencup'],4);
     * </code>
     *
     * @param Array|\ArrayObject|Object $row
     * @param int $toId
     * @return int
     */
    public function addNode($row, $toId);

    /**
     * 移动节点
     *
     * <code>
     *      $table->moveNode(2, 3)
     * </code>
     *
     * @param int $fromId
     * @param int $toId
     * @return int
     */
    public function moveNode($fromId, $toId);

    /**
     * 根据节点id获取父节点
     *
     * <code>
     *      $table->getParentNodeById(12);
     *      $table->getParentNodeById(12,2)
     *      $table->getParentNodeById(12,2,'ASC')
     *      $table->getParentNodeById(12,2,'ASC',['id','name'])
     * </code>
     *
     * @param int $id
     * @param null | int $depth
     * @param string $order
     * @param array $columns
     * @return \Zend\Db\ResultSet\ResultSet
     */
    public function getParentNodeById($id, $depth = null, $order = 'ASC', $columns = ['*']);

    /**
     * 根据节点id获取子节点
     *
     * <code>
     *      $table->getChildNodeById(12);
     *      $table->getChildNodeById(12,2)
     *      $table->getChildNodeById(12,2,'ASC')
     *      $table->getChildNodeById(12,2,'ASC',['id','name'])
     * </code>
     *
     * @param int $id
     * @param null | int $depth
     * @param string $order
     * @param array $columns
     * @return \Zend\Db\ResultSet\ResultSet
     */
    public function getChildNodeById($id, $depth = null, $order = 'ASC', $columns = ['*']);


    /**
     * 根据节点id 删除所有子元素
     *
     * <code>
     *      $table->deleteChildNodeById(1);
     *      $table->deleteChildNodeById(1,false);
     * </code>
     *
     * @param int | Traversable | array $idOrIds
     * @param bool $itself
     * @return int | null
     */
    public function deleteChildNodeById($idOrIds, $itself = true);


    /**
     * 根据节点id 删除节点
     *
     * <code>
     *      $table->deleteNodeById(1);
     * </code>
     *
     * @param int | \Traversable | array $idOrIds
     * @return int | null
     */
    public function deleteNodeById($idOrIds);

}