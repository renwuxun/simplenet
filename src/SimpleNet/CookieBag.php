<?php

/**
 * Created by PhpStorm.
 * User: wuxun.ren
 * Date: 5-22 00022
 * Time: 13:57
 */
class SimpleNet_CookieBag {

    /**
     * @var SimpleNet_Cookie[]
     */
    protected $cookies = array();

    /**
     * @param SimpleNet_Cookie $cookie
     * @return $this
     */
    public function put(SimpleNet_Cookie $cookie) {
        $this->cookies[$cookie->getKey()] = $cookie;
        return $this;
    }

    /**
     * @param string $key
     * @return null|SimpleNet_Cookie
     */
    public function get($key) {
        return isset($this->cookies[$key]) ? $this->cookies[$key]: null;
    }

    /**
     * @return $this
     */
    public function applyTimeout() {
        foreach ($this->cookies as $key => $cookie) {
            if ($cookie->isExpired()) {
                unset($this->cookies[$key]);
            }
        }
        return $this;
    }

    /**
     * @return string
     */
    public function prepare4request() {
        $arr = array();
        foreach ($this->cookies as $cookie) {
            $arr[] = $cookie->formatted4request();
        }
        return implode('; ', $arr);
    }

    /**
     * @return string[]
     */
    public function prepare4response() {
        $strs = array();
        foreach ($this->cookies as $cookie) {
            $strs[] = $cookie->formatted4response();
        }
        return $strs;
    }
}
