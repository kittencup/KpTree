<?php
/**
 * Kittencup Module
 *
 * @date 2014 14-6-3 下午2:48
 * @copyright Copyright (c) 2014-2015 Kittencup. (http://www.kittencup.com)
 * @license   http://kittencup.com
 */


namespace KpTree\View\Helper;


use Zend\Form\View\Helper\AbstractHelper;

class NestedSelect extends AbstractHelper
{
    protected $selectWrapper = '<select %s>%s</select>';
    protected $optionWrapper = '<option %s>%s</option>';

    public function __invoke($data)
    {
        return $this->render($data);
    }

    public function render($data)
    {
        $optionHtml = '';

        foreach ($data as $row) {
            $optionHtml .= sprintf($this->optionWrapper, '', '(' . $row['id'] . ')' . str_repeat('　', $row['depth']) . $row['name']);
        }

        return sprintf($this->selectWrapper, '', $optionHtml);
    }
}

?>
