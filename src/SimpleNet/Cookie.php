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
    protected $ext;

    /**
     * SimpleNet_Cookie constructor.
     * @param string $key
     * @param string $value urlencode
     * @param int $expiresAt
     * @param bool|string $domain
     * @param string $path
     * @param string $ext
     */
    public function __construct($key, $value = '', $expiresAt = 0, $domain = false, $path = '/', $ext = '') {
        $this->key = $key;
        $this->value = $value;
        $this->expires = (int)$expiresAt;
        $this->domain = $domain;
        $this->path = $path;
        $this->ext = $ext;
    }

    public function isExpired() {
        return ($this->expires != 0 && time() > $this->expires);
    }

    public function formatted4response() {
        $str = $this->key . '=' . $this->value;
        $this->expires>0 && $str .= '; expires=' . gmdate('D, d-M-Y H:i:s T', $this->expires);
        $this->domain && $str .= '; domain=' . $this->domain;
        $this->path && $str .= '; path=' . $this->path;
        $this->ext && $str .= '; ' . $this->ext;
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
            $key = '';
            $val = '';
            $expires = 0;
            $domain = false;
            $path = '/';
            $exts = array();

            $options = explode(';', $v);
            foreach ($options as $option) {
                $option = trim($option);
                $optionkv = explode('=', $option, 2);
                switch ($optionkv[0]) {
                    case 'expires':
                        $expires = strtotime($optionkv[1]);
                        break;
                    case 'domain':
                        $domain = $optionkv[1];
                        break;
                    case 'path':
                        $path = $optionkv[1];
                        break;
                    case 'Max-Age'://懒得管它
                        break;
                    case 'secure':
                    case 'HttpOnly':
                        $exts[] = $optionkv[0];
                        break;
                    default:
                        $key = $optionkv[0];
                        $val = $optionkv[1];
                }
            }

            $cookies[] = new self($key, $val, $expires, $domain, $path, implode('; ', $exts));
        }

        return $cookies;
    }

    /**
     * @return string
     */
    public function getKey() {
        return $this->key;
    }

    /**
     * @return string
     */
    public function getValue() {
        return $this->value;
    }

    /**
     * @return int
     */
    public function getExpires() {
        return $this->expires;
    }

    /**
     * @return bool|string
     */
    public function getDomain() {
        return $this->domain;
    }

    /**
     * @return string
     */
    public function getPath() {
        return $this->path;
    }
}
