<?php


class Spectrocoin_Spectrocoin_Block_LastPayment extends Mage_Checkout_Block_Cart_Totals{

    public function needDisplayBaseGrandtotal(){
        return false;
    }

}