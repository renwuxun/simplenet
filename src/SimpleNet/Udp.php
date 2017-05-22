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
    }

    /**
     * 请自行确保(!isClose())
     * @param string $msg
     * @param int $timeoutsec
     * @return bool
     */
    public function send($msg, $timeoutsec = 5) {
        $this->sendData = '';

        $this->error = '';

        $length = strlen($msg);
        $wrote = 0;

        do {
            if (!@stream_set_timeout($this->fp, $timeoutsec)) {
                $this->error = 'set fwrite timeout error';
                $this->close();
                return false;
            }
            $_wrote = fwrite($this->fp, $msg, $length-$wrote);
            if ($_wrote === false) {
                $this->error = 'fwrite() error [send]';
                $info = @stream_get_meta_data($this->fp);
                if (isset($info['timed_out']) && $info['timed_out']) {
                    $this->error = 'udp send timeout';
                }
                $this->close();
                return false;
            }
            $wrote += $_wrote;
            $this->sendData .= substr($msg, 0, $_wrote);
            $msg = substr($msg, $_wrote);
        } while ($wrote < $length);

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
