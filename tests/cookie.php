<?php
/**
 * Created by PhpStorm.
 * User: wuxun.ren
 * Date: 5-23 00023
 * Time: 9:28
 */

require dirname(__DIR__).'/vendor/autoload.php';


$responseHeader = "HTTP/1.1 200 OK\r
Server: bfe/1.0.8.18\r
Date: Tue, 23 May 2017 01:28:24 GMT\r
Content-Type: text/html; charset=utf-8\r
Transfer-Encoding: chunked\r
Connection: keep-alive\r
Vary: Accept-Encoding\r
Cache-Control: private\r
Cxy_all: baidu+822722681271d6c4b46d7ad615954ecc\r
Expires: Tue, 23 May 2017 01:27:41 GMT\r
X-Powered-By: HPHP\r
X-UA-Compatible: IE=Edge,chrome=1\r
Strict-Transport-Security: max-age=172800\r
BDPAGETYPE: 1\r
BDQID: 0xcc92d5f20009c530\r
BDUSERID: 0\r
Set-Cookie: aaa=0; Max-Age=7200; path=/; secure; HttpOnly\r
Set-Cookie: BD_HOME=0; path=/\r
Set-Cookie: H_PS_PSSID=22778_1433_21090_18560_22159; path=/; domain=.baidu.com\r
Content-Encoding: gzip\r
Set-Cookie: __bsi=2373239043788800130_00_0_I_R_3_0303_C02F_N_I_I_0; expires=Tue, 23-May-17 01:28:29 GMT; domain=www.baidu.com; path=/\r\n\r\n";

$cs = SimpleNet_Cookie::findCookies($responseHeader);
foreach ($cs as $c) {
    echo $c->formatted4request().' [is expires: '.(int)$c->isExpired().'] ';
    echo $c->formatted4response().PHP_EOL;
}
