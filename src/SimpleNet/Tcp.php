<?php



class SimpleNet_Tcp {

    protected $fp;
    protected $host = '';
    protected $port = 0;
    protected $timeoutsec = 5;

    protected $errno = 0;
    protected $errstr = '';

    public function connect() {
        $this->errno = 0;
        $this->errstr = '';
        $this->fp = null;

        do {
            /**
             * http://php.net/manual/zh/function.fsockopen.php
             * Note:
             * 注意：如果你要对建立在套接字基础上的读写操作设置操作时间设置连接时限，
             * 请使用stream_set_timeout()，
             * fsockopen()的连接时限（timeout）的参数仅仅在套接字连接的时候生效。
             */
            $this->fp = @fsockopen($this->host, $this->port, $this->errno, $this->errstr, $this->timeoutsec);
            if (false === $this->fp) {
                if (0 == $this->errno) {
                    $this->errno = 30;
                    $this->errstr = 'error before connect';
                } else {
                    switch ($this->errno) {
                        case -3:
                            $this->errstr = "socket creation failed (-3)";
                            break;
                        case -4:
                            $this->errstr = "dns lookup failure (-4)";
                            break;
                        case -5:
                            $this->errstr = "connection refused or timed out (-5)";
                            break;
                        default:;
                    }
                }
                break;
            }
            if (is_resource($this->fp)) {
                stream_set_timeout($this->fp, $this->timeoutsec);
            }
        } while (0);

        return is_resource($this->fp);
    }

    /**
     * 已主动关闭|从未连接
     * @return bool
     */
    public function isClose() {
        return !is_resource($this->fp);
    }

    public function close() {
        if (is_resource($this->fp)) {
            fclose($this->fp);
        }
    }

    /**
     * 请自行确保(!isClose())
     * @param $msg
     * @return int
     */
    public function send($msg) {
        $this->errno = 0;
        $this->errstr = '';

        $length = strlen($msg);
        $wrote = 0;

        do {
            $_wrote = fwrite($this->fp, $msg, $length-$wrote);
            if ($_wrote === false) {
                $this->errno = 50;
                $this->errstr = 'fwrite() error [send]';
                $info = @stream_get_meta_data($this->fp);
                if (isset($info['timed_out']) && $info['timed_out']) {
                    $this->errno = 60;
                    $this->errstr = 'tcp send timeout';
                }
                $this->close();
                break;
            }
            $wrote += $_wrote;
            $msg = substr($msg, $wrote);
        } while ($wrote < $length);

        return $wrote;
    }

    public function recv($length) {
        $this->errno = 0;
        $this->errstr = '';

        $got = 0;
        $str = '';

        do {
            $tmp = fread($this->fp, $length - $got);
            if (false === $tmp) {
                $this->errno = 80;
                $this->errstr = 'fread() error [recv]';
                $info = @stream_get_meta_data($this->fp);
                if (isset($info['timed_out']) && $info['timed_out']) {
                    $this->errno = 70;
                    $this->errstr = 'connection recv timeout [recv]';
                }
                $this->close();
                break;
            }
            $str .= $tmp;
            $got += strlen($tmp);
        } while ($got < $length && !$this->feof());
        return $str;
    }

    public function fgets($maxLength = null) {
        $this->errno = 0;
        $this->errstr = '';

        $str = fgets($this->fp, $maxLength);

        if (false === $str) {
            $this->errno = 110;
            $this->errstr = 'fgets() error [fgets]';
            $info = @stream_get_meta_data($this->fp);
            if (isset($info['timed_out']) && $info['timed_out']) {
                $this->errno = 110;
                $this->errstr = 'connection recv timeout [fgets]';
            }
            $this->close();
            return $str;
        }

        return $str;
    }

    public function feof() {
        return @feof($this->fp);
    }

    /**
     * @return int
     */
    public function getErrno() {
        return $this->errno;
    }

    /**
     * @return string
     */
    public function getErrstr() {
        return $this->errstr;
    }

    /**
     * @return resource
     */
    public function getFp() {
        return $this->fp;
    }

    /**
     * @param resource $fp
     * @return $this
     */
    public function setFp($fp) {
        $this->fp = $fp;
        return $this;
    }

    /**
     * @return string
     */
    public function getHost() {
        return $this->host;
    }

    /**
     * @param string $host
     * @return $this
     */
    public function setHost($host) {
        $this->host = $host;
        return $this;
    }

    /**
     * @return int
     */
    public function getPort() {
        return (int)$this->port;
    }

    /**
     * @param int $port
     * @return $this
     */
    public function setPort($port) {
        $this->port = (int)$port;
        return $this;
    }

    /**
     * @return int
     */
    public function getTimeoutsec() {
        return $this->timeoutsec;
    }

    /**
     * @param int $timeoutsec
     * @return $this
     */
    public function setTimeoutsec($timeoutsec) {
        $this->timeoutsec = $timeoutsec;
        return $this;
    }

}