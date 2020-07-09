<?php
/**
 * Created by PhpStorm.
 * User: Janna
 * Date: 4/5/18
 * Time: 1:08 PM
 */

namespace Paynamics\Gateway\Model\Config;

class Mode implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        // return your data
        return [['value' => 'Test', 'label' => __('Test')], ['value' => 'Live', 'label' => __('Live')]];
    }
}