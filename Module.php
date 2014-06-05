<?php
/**
 * Kittencup Module
 *
 * @date 2014 14-6-1 下午5:35
 * @copyright Copyright (c) 2014-2015 Kittencup. (http://www.kittencup.com)
 * @license   http://kittencup.com
 */
namespace KpTree;

use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Zend\ModuleManager\Feature\ControllerProviderInterface;
use Zend\ModuleManager\Feature\ServiceProviderInterface;
use Zend\ModuleManager\Feature\ViewHelperProviderInterface;

class Module implements AutoloaderProviderInterface,
    ControllerProviderInterface,
    ServiceProviderInterface,
    ViewHelperProviderInterface
{

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getAutoloaderConfig()
    {
        return [
            'Zend\Loader\StandardAutoloader' => [
                'namespaces' => [
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ],
            ]
        ];
    }

    public function getControllerConfig()
    {
        return [
            'invokables' => [
                'KpTree\Controller\Test' => 'KpTree\Controller\TestController',
            ]
        ];
    }

    public function getViewHelperConfig(){
        return [
            'invokables'=>[
                'nestedSelect'=>'KpTree\View\Helper\NestedSelect'
            ]
        ];
    }

    public function getServiceConfig()
    {
        return [
            'invokables'=>[
                'KpTree\Model\NestedTable'=>'KpTree\Model\NestedTable',
                'KpTree\Model\PathEnumTable'=>'KpTree\Model\PathEnumTable',
                'KpTree\Model\ClosureTable'=>'KpTree\Model\ClosureTable'
            ]
        ];
    }

}