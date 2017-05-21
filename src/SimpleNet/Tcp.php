<?php



class SimpleNet_Tcp {

    protected $fp;
    protected $host = '';
    protected $port = -1;

    protected $error = '';

    protected $sendData = '';
    protected $recvData = '';

    public function __construct($host, $port = -1) {
        $this->host = $host;
        $this->port = $port;
    }

    /**
     * @param int $timeoutsec
     * @return bool
     */
    public function connect($timeoutsec = 5) {
        $this->error = '';
        $this->fp = null;

        do {
            /**
             * http://php.net/manual/zh/function.fsockopen.php
             * Note:
             * 注意：如果你要对建立在套接字基础上的读写操作设置操作时间设置连接时限，
             * 请使用stream_set_timeout()，
             * fsockopen()的连接时限（timeout）的参数仅仅在套接字连接的时候生效。
             */
            $this->fp = @fsockopen($this->host, $this->port, $errno, $this->error, $timeoutsec);
            if (false === $this->fp) {
                if (0 == $errno) {
                    $this->error = "error happened before connect, please check your host {$this->host}";
                } else {
                    switch ($errno) {
                        case -3:
                            $this->error = "socket creation failed ($errno)";
                            break;
                        case -4:
                            $this->error = "dns lookup failure ($errno)";
                            break;
                        case -5:
                            $this->error = "connection refused or timed out ($errno)";
                            break;
                        case 10060:
                            $this->error = "no server running port {$this->port} ($errno)";
                            break;
                        default:
                            $this->error = "could not open connection to {$this->host}:{$this->port} ($errno)";
                    }
                }
                break;
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
     * @param string $msg
     * @param int $timeoutsec
     * @return bool
     */
    public function send($msg, $timeoutsec = 5) {
        $this->sendData = $msg;
        unset($msg);

        $this->error = '';

        $length = strlen($this->sendData);
        $wrote = 0;

        do {
            if (!@stream_set_timeout($this->fp, $timeoutsec)) {
                $this->error = 'set fwrite timeout error';
                $this->close();
                return false;
            }
            $_wrote = fwrite($this->fp, $this->sendData, $length-$wrote);
            if ($_wrote === false) {
                $this->error = 'fwrite() error [send]';
                $info = @stream_get_meta_data($this->fp);
                if (isset($info['timed_out']) && $info['timed_out']) {
                    $this->error = 'tcp send timeout';
                }
                $this->close();
                return false;
            }
            $wrote += $_wrote;
            $this->sendData = substr($this->sendData, $wrote);
        } while ($wrote < $length);

        return true;
    }

    /**
     * @param $length
     * @param int $timeoutsec
     * @return bool
     */
    public function recv($length, $timeoutsec = 5) {
        $this->error = '';

        $got = 0;
        $this->recvData = '';

        do {
            if (!@stream_set_timeout($this->fp, $timeoutsec)) {
                $this->error = 'set fread timeout error';
                $this->close();
                return false;
            }
            $tmp = fread($this->fp, $length - $got);
            if (false === $tmp) {
                $this->error = 'fread() error [recv]';
                $info = @stream_get_meta_data($this->fp);
                if (isset($info['timed_out']) && $info['timed_out']) {
                    $this->error = 'connection recv timeout [recv]';
                }
                $this->close();
                return false;
            }
            $this->recvData .= $tmp;
            $got += strlen($tmp);
        } while ($got < $length && !$this->feof());
        return true;
    }

    /**
     * @param null $maxLength
     * @param int $timeoutsec
     * @return bool
     */
    public function fgets($maxLength = null, $timeoutsec = 5) {
        $this->error = '';

        if (!@stream_set_timeout($this->fp, $timeoutsec)) {
            $this->error = 'set fget timeout error';
            $this->close();
            return false;
        }

        $this->recvData = fgets($this->fp, $maxLength);

        if (false === $this->recvData) {
            $this->error = 'fgets() error [fgets]';
            $info = @stream_get_meta_data($this->fp);
            if (isset($info['timed_out']) && $info['timed_out']) {
                $this->error = 'connection recv timeout [fgets]';
            }
            $this->close();
            return false;
        }

        return true;
    }

    public function feof() {
        return @feof($this->fp);
    }

    /**
     * @return string
     */
    public function getError() {
        return $this->error;
    }

    /**
     * @return string
     */
    public function getHost() {
        return $this->host;
    }

    /**
     * @return int
     */
    public function getPort() {
        return (int)$this->port;
    }

    /**
     * @return string
     */
    public function getSendData() {
        return $this->sendData;
    }

    /**
     * @return string
     */
    public function getRecvData() {
        return $this->recvData;
    }
}