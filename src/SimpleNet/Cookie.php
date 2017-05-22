<?php

/**
 * Created by PhpStorm.
 * User: renwuxun
 * Date: 2017/5/22 0022
 * Time: 23:59
 */
class SimpleNet_Cookie {

    protected $key;
    protected $value;
    protected $expires;
    protected $domain;
    protected $path;

    /**
     * SimpleNet_Cookie constructor.
     * @param string $key
     * @param string $value
     * @param int $expiresAt
     * @param bool $domain
     * @param string $path
     */
    public function __construct($key, $value = '', $expiresAt = 0, $domain = false, $path = '/') {
        $this->key = $key;
        $this->value = $value;
        $this->expires = (int)$expiresAt;
        $this->domain = $domain;
    }

    public function isExpires() {
        return time() > $this->expires;
    }

    public function formatted4response() {
        $str = $this->key . '=' . $this->value;
        $this->expires>0 && $str .= '; expires=' . gmdate('D, d-M-Y H:i:s T', $this->expires);
        $this->domain && $str .= '; domain=' . $this->domain;
        $this->path && $str .= '; path=' . $this->path;
        return $str;
    }

    public function formatted4request() {
        return $this->key . '=' .$this->value;
    }

    /**
     * @param string $responseHeader
     * @return SimpleNet_Cookie[]
     */
    public static function findCookies($responseHeader) {
        preg_match_all('/Set\-Cookie:\s*(.*?)(?=\r\n)/i', $responseHeader, $m);
        if (empty($m[1])) {
            return array();
        }
        $cookies = array();
        foreach ($m[1] as $v) {
            $p = array();
            $kvs = explode(';', $v);
            foreach ($kvs as $kv) {
                $kv = trim($kv);
                $k2v = explode('=', $kv);
                $p[$k2v[0]] = $k2v[1];
            }
            !isset($p['expires']) && $p['expires'] = 0;
            !isset($p['domain']) && $p['domain'] = false;
            !isset($p['path']) && $p['path'] = '/';
            $expires = $p['expires'];unset($p['expires']);
            $domain = $p['domain'];unset($p['domain']);
            $path = $p['path'];unset($p['path']);
            $key = '';
            $val = '';
            foreach ($p as $_k=>$_v) {
                $key = $_k;
                $val = $_v;
                break;
            }
            $cookies[] = new self($key, $val, $expires, $domain, $path);
        }

        return $cookies;
    }
}