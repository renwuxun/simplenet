<?php
/**
 * Created by PhpStorm.
 * User: renwuxun
 * Date: 2017/5/22 0022
 * Time: 0:38
 */


require dirname(__DIR__).'/vendor/autoload.php';


$http = new SimpleNet_Http();

$http->enableCookie()
    ->setTcp(new SimpleNet_Tcp('g.phpoy.com', 80));

echo '----------------------------------default'.PHP_EOL;
if ($http->request('/')) {
    echo $http->getRecv().PHP_EOL;
} else {
    echo $http->getError().PHP_EOL;
}

echo '----------------------------------Encoding'.PHP_EOL;
if ($http->request('/', array('Accept-Encoding'=>'gzip, deflate'))) {
    echo $http->getRecv().PHP_EOL;
} else {
    echo $http->getError().PHP_EOL;
}

echo '----------------------------------404'.PHP_EOL;
if ($http->request('/abc')) {
    echo $http->getRecv().PHP_EOL;
} else {
    echo $http->getError().PHP_EOL;
}


$http->getTcp()->close();
$http->setTcp(new SimpleNet_Tcp('gg.phpoy.com', 80));

echo '----------------------------------host error'.PHP_EOL;
if ($http->request('/')) {
    echo $http->getRecv().PHP_EOL;
} else {
    echo $http->getError().PHP_EOL;
}


$http->getTcp()->close();
$http->setTcp(new SimpleNet_Tcp('g.phpoy.com', 8000));

echo '----------------------------------port error'.PHP_EOL;
if ($http->request('/')) {
    echo $http->getRecv().PHP_EOL;
} else {
    echo $http->getError().PHP_EOL;
}
$http->getTcp()->close();
