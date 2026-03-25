<?php
header('Content-Type: application/json');
session_start();

$prizes = [
    ['id' => 1, 'name' => '安全帽 9 折', 'weight' => 20, 'code' => 'HELMET10', 'angle' => 30],
    ['id' => 2, 'name' => '騎士節 8 折', 'weight' => 5,  'code' => 'RIDER20',  'angle' => 90],
    ['id' => 3, 'name' => '滿2000折300', 'weight' => 10, 'code' => 'SAVE300',  'angle' => 150],
    ['id' => 4, 'name' => '全站免運',     'weight' => 15, 'code' => 'FREE',     'angle' => 210],
    ['id' => 5, 'name' => '新手 NT$100',  'weight' => 20, 'code' => 'NEW100',   'angle' => 270],
    ['id' => 6, 'name' => '銘謝惠顧',     'weight' => 30, 'code' => null,       'angle' => 330],
];

// 加權隨機
$totalWeight = array_sum(array_column($prizes, 'weight'));
$rand = mt_rand(1, $totalWeight);

$currentWeight = 0;
$winningPrize = null;

foreach ($prizes as $prize) {
    $currentWeight += $prize['weight'];
    if ($rand <= $currentWeight) {
        $winningPrize = $prize;
        break;
    }
}

echo json_encode($winningPrize);