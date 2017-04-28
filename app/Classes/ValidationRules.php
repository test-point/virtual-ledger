<?php

class ValidationRules
{

    /**
     * Validate ABN number
     *
     * @param $abn
     * @return boolean
     */
    public function isValidAbn($field, $abn)
    {
        $weights = array(10, 1, 3, 5, 7, 9, 11, 13, 15, 17, 19);
        // Strip non-numbers from the acn
        $abn = preg_replace('/[^0-9]/', '', $abn);
        // Check abn is 11 chars long
        if (strlen($abn) != 11) {
            return false;
        }
        // Subtract one from first digit
        $abn[0] = ((int)$abn[0] - 1);
        // Sum the products
        $sum = 0;
        foreach (str_split($abn) as $key => $digit) {
            $sum += ($digit * $weights[$key]);
        }
        error_log($sum);
        if (($sum % 89) != 0) {
            dump($sum);
            return false;
        }
        return true;
    }
}