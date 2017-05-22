<?php

/**
 * Created by PhpStorm.
 * User: wuxun.ren
 * Date: 5-22 00022
 * Time: 13:57
 */
class SimpleNet_CookieBag {

    protected $cookies = array();

    /**
     * @param string $key
     * @param string $value
     * @param int|string $expires
     * @return $this
     */
    public function put($key, $value, $expires = '') {
        $endtime = 2145891661;
        if ($expires != '') {
            if (is_numeric($expires)) {
                $endtime = (int)$expires;
            } else {
                $endtime = strtotime($expires);
            }
        }
        $this->cookies[$key] = $endtime.$value;
        return $this;
    }

    /**
     * @param string $key
     * @return string
     */
    public function get($key) {
        return isset($this->cookies[$key]) ? substr($this->cookies[$key], 10): '';
    }

    /**
     * @return $this
     */
    public function applyTimeout() {
        $now = time();
        foreach ($this->cookies as $key => $val) {
            $time = (int)substr($val, 0, 10);
            if ($time < $now) {
                unset($this->cookies[$key]);
            }
        }
        return $this;
    }

    /**
     * @return string
     */
    public function prepare4request() {
        $s = '';
        foreach ($this->cookies as $key=>$val) {
            $s .= $key.'='.substr($val, 10).',';
        }
        return rtrim($s, ',');
    }

    /**
     * @return string
     */
    public function prepare4response() {
        // todo
        return '';
    }
}
