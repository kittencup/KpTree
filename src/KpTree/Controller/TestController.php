<?php
/**
 * Kittencup Module
 *
 * @date 2014 14-6-1 下午5:46
 * @copyright Copyright (c) 2014-2015 Kittencup. (http://www.kittencup.com)
 * @license   http://kittencup.com
 */

namespace KpTree\Controller;

use Zend\Filter\Null;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class TestController extends AbstractActionController
{

    public function indexAction()
    {

        $table = $this->getServiceLocator()->get('KpTree\Model\ClosureTable');

        $table->addNode(['name' => 'extjs'], 4);
        //var_dump($table->moveNode(3,7));
        //$table->deleteNodeById(3);
        $vm = new ViewModel();
        $vm->setTerminal(true);
        $vm->setVariables([
            'tree' => $table->getParentNodeById(7),
            'tree2' => $table->getChildNodeById(1)
        ]);
        return $vm;
    }

}