<?php
/**
 * Kittencup Module
 *
 * @date 2014 14-6-5 下午9:46
 * @copyright Copyright (c) 2014-2015 Kittencup. (http://www.kittencup.com)
 * @license   http://kittencup.com
 */

namespace KpTree\Model;

/**
 * Interface TreeTableDebugInterface
 * @package KpTree\Model
 */
interface TreeTableDebugInterface
{
    /**
     * 添加调试特性
     */
    public function addDebugFeature();

    /**
     * 设置是否开启调试
     * @param $openDebug
     */
    public static function setOpenDebug($openDebug);

    /**
     * 获取是否开启调试
     * @return mixed
     */
    public static function getOpenDebug();
}