<?php

$letters = [
    'a',
    'b',
    'c',
    'd',
    'e',
    'f',
    'g',
    'h',
    'i',
    'j',
    'k',
    'l',
    'm',
    'n',
    'o',
    'p',
    'q',
    'r',
    's',
    't',
    'u',
    'v',
    'w',
    'x',
    'y',
    'z'
];

$all = [];
foreach ($letters as $letter) {
    $contents = file_get_contents('files/bible-names/' . $letter . '.json');
    $arr = json_decode($contents);
    echo $letter . ' -> ' . count($arr ?? []) . PHP_EOL;
    array_push($all, ...($arr ?? []));
}
echo 'all -> ' . count($all) . PHP_EOL;
file_put_contents('files/bible-names/all-names.json', json_encode($all));