<?php


class SimpleNet_Udp {
    
    protected $host = '';
    protected $port = -1;
    protected $error = '';
    protected $fp;
    protected $sendData = '';

    public function __construct($host, $port) {
        $this->host = $host;
        $this->port = $port;
    }

    /**
     * @param int $timeoutsec
     */
    public function connect($timeoutsec = 3) {
        do {
            /**
             * http://php.net/manual/zh/function.fsockopen.php
             * Note:
             * 注意：如果你要对建立在套接字基础上的读写操作设置操作时间设置连接时限，
             * 请使用stream_set_timeout()，
             * fsockopen()的连接时限（timeout）的参数仅仅在套接字连接的时候生效。
             */
            $this->fp = @fsockopen('udp://' . $this->host, $this->port, $errno, $this->error, $timeoutsec);
            if (false === $this->fp) {
                if (0 == $errno) {
                    $this->error = 'error before connect';
                } else {
                    switch ($errno) {
                        case -3:
                            $this->error = "socket creation failed (-3)";
                            break;
                        case -4:
                            $this->error = "dns lookup failure (-4)";
                            break;
                        case -5:
                            $this->error = "connection refused or timed out (-5)";
                            break;
                        default:;
                    }
                }
                break;
            }
        } while (0);
    }

    /**
     * @param $msg
     * @param int $timeoutsec
     * @return bool
     */
    public function send($msg, $timeoutsec = 3) {
        $this->sendData = $msg;
        unset($msg);
        $length = strlen($this->sendData);
        $wrote = 0;

        while ($wrote < $length) {
            if (!@stream_set_timeout($this->fp, $timeoutsec)) {
                $this->error = 'set timeout error';
                return false;
            }
            $wrote += fwrite($this->fp, $this->sendData, $length - $wrote);
            $this->sendData = substr($this->sendData, $wrote);
            $info = stream_get_meta_data($this->fp);
            if ($info['timed_out']) {
                $this->error = 'udp send timeout';
                return false;
            }
        }

        return true;
    }

    public function close() {
        if (is_resource($this->fp)) {
            fclose($this->fp);
        }
    }

    public function isConnected() {
        return is_resource($this->fp);
    }

    /**
     * @return string
     */
    public function getSendData() {
        return $this->sendData;
    }
}