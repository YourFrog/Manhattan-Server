<?php
//Copy from tibia wiki
// link https://tibia.fandom.com/wiki/Liquid_IDs
$rawData = "Water (Liquid)	1
Wine	2
Beer	3
Mud	4
Blood	5
Slime (Liquid)	6
Oil	7
Urine	8
Milk	9
Manafluid	10
Lifefluid	11
Lemonade	12
Rum	13
Fruit Juice	14
Coconut Milk	15
Mead	16
Tea	17
Ink (Liquid)	18";

$data = explode("\r\n", $rawData);
$items = array_reduce($data, function($ret, $itemData) {
    [$itemName, $ids] = explode("\t", $itemData);
    $ret[$itemName] = array_map('intval', explode(',', $ids));

    return $ret;
}, []);

$output = '';

foreach($items as $itemName => $clientIds) {
    foreach($clientIds as $index => $id) {
        $output .= strtr('const NAMESUFFIX = ID;', [
            'NAME' => strtoupper(str_replace([' ', '\'', '(', ')', '-', ',', '.'], '_', $itemName)),
            'SUFFIX' => count($clientIds) == 1 ? '' : '_' . ($index + 1),
            'ID' => $id
        ]);

        $output .= PHP_EOL;
    }
}

//Outputs list of consts to copy to the class
echo $output;