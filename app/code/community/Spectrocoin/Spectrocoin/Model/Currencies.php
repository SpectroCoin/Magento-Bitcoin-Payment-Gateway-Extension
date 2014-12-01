<?php

class Spectrocoin_Spectrocoin_Model_Currencies
{
    public function toOptionArray()
    {
        return array(
            array('value' => 'BTC', 'label'=>Mage::helper('adminhtml')->__('Bitcoin')),
            array('value' => 'EUR', 'label'=>Mage::helper('adminhtml')->__('Euro')),
        );
    }
}