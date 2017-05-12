<?php



if (!function_exists('gzdecode')) {
    function gzdecode($str) {
        return gzinflate(substr($str,10,-8));
    }
}

/**
 * Created by PhpStorm.
 * User: mofan
 * Date: 2016/12/14 0014
 * Time: 17:24
 */
class SimpleNet_Http {

    /**
     * @var SimpleNet_Tcp
     */
    protected $tcp;

    protected $errno = 0;
    protected $errstr = '';

    protected $lastSendHeader = '';
    protected $lastSendBody = '';
    protected $lastRecvHeader = '';
    protected $lastRecvBody = '';

    protected $cookies = array();
    protected $enableCookie = true;

    protected $statusCode = 0;
    protected $statusText = '';

    /**
     * @return $this
     */
    public function enableCookie() {
        $this->enableCookie = true;
        return $this;
    }

    /**
     * @return $this
     */
    public function disableCookie() {
        $this->enableCookie = false;
        return $this;
    }

    protected function checkCookie() {
        foreach ($this->cookies as $key => $val) {
            $time = (int)substr($val, 0, 10);
            if ($time < time()) {
                unset($this->cookies[$key]);
            }
        }
        if ($this->lastRecvHeader != '') {
            preg_match_all('/Set\-Cookie:\s*([^=]+)=([^;]+);(?:\s*expires=([^;]+);)?/is', $this->lastRecvHeader, $m);
            foreach ($m[1] as $k=>$v) {
                $endtime = 2145891661;
                if ($m[3][$k] != '') {
                    $endtime = strtotime($m[3][$k]);
                }
                $this->cookies[$v] = $endtime.$m[2][$k];
            }
        }
    }

    public function request($uri = '/', $headers = array(), $data = '', $method = '') {
        if ($this->enableCookie) {
            $this->checkCookie();
            foreach ($this->cookies as $key => $val) {
                if (!isset($headers['Cookie'])) {
                    $headers['Cookie'] = '';
                }
                if ('' != $headers['Cookie']) {
                    $headers['Cookie'] .= '; ';
                }
                $headers['Cookie'] .= $key.'='.substr($val, 10);
            }
        }

        $sendBody = '';
        if (!empty($data)) {
            $sendBody = $data;
            unset($data);
            if (is_array($sendBody)) {
                $sendBody = http_build_query($sendBody);
            }
        }
        $sendHeader = self::buildHeader($uri, $headers, strlen($sendBody), $method);
        unset($headers);

        $r = $this->requestRaw($sendHeader.$sendBody);

        $pos = strpos($r, "\r\n\r\n");
        if ($pos < 11) { //HTTP/1.1 404
            if ($this->errno == 0) {
                $this->errno = 300;
                $this->errstr = 'bad response: ['.$r.']';
            }
            $this->tcp->close();
            return '';
        }

        $this->lastSendHeader = $sendHeader;
        $this->lastSendBody = $sendBody;
        $this->lastRecvHeader = substr($r, 0, $pos+4);
        $this->lastRecvBody = substr($r, $pos+4);
        unset($sendHeader,$sendBody,$r);

        if (0 != $this->errno
            || $this->tcp->feof()
            || preg_match('/Connection:\s*close/i', $this->lastRecvHeader)
        ) {
            $this->tcp->close();
        }

        if (preg_match('/Content-Encoding:\s*(gzip|deflate)/i', $this->lastRecvHeader, $m)) {
            switch ($m[1]) {
                case 'gzip':
                    $this->lastRecvBody = gzdecode($this->lastRecvBody);
                    break;
                case 'deflate':
                    $this->lastRecvBody = gzinflate($this->lastRecvBody);
                    break;
                default:
                    throw new Exception('unsupported Content-Encoding: '.$m[1]);
            }
        }

        $this->statusCode = 0;
        $this->statusText = '';
        if (preg_match('/^HTTP\/[^\s]+\s+(\d+)\s+([^\r\n]+)\r\n/i', $this->lastRecvHeader, $m)) {
            $this->statusCode = (int)$m[1];
            $this->statusText = trim($m[2]);
        }

        return $this->lastRecvBody;
    }

    public function requestRaw($msg) {
        $this->errno = 0;
        $this->errstr = '';

        if ($this->tcp->isClose()) {
            if (!$this->tcp->connect()) {
                $this->errno = $this->tcp->getErrno();
                $this->errstr = $this->tcp->getErrstr();
                throw new RuntimeException($this->tcp->getErrstr());
            }
        }

        $header = '';
        $body = '';
        do {
            $this->tcp->send($msg);
            if (0 != $this->tcp->getErrno()) {
                $this->errno = $this->tcp->getErrno();
                $this->errstr = $this->tcp->getErrstr();
                break;
            }

            $header = $this->readHeader();
            if (0 != $this->tcp->getErrno()) {
                $this->errno = $this->tcp->getErrno();
                $this->errstr = $this->tcp->getErrstr();
                break;
            }

            if (preg_match('/Content-Length:\s*(\d+)/i', $header,$m)){
                $body = $this->tcp->recv(intval($m[1]));
            } elseif (preg_match('/Transfer-Encoding:\s*chunked/i', $header)) {
                $body = $this->readChunkedBody();
            } else {
                $this->errno = 200;
                $this->errstr = 'parse header error [readBody]';
                break;
            }
            if ($this->tcp->getErrno() != 0) {
                $this->errno = $this->tcp->getErrno();
                $this->errstr = $this->tcp->getErrstr();
                break;
            }
        } while (0);

        return $header.$body;
    }

    /**
     * @param string $uri
     * @param array $headers
     * @param int $bodyLength
     * @param string $method
     * @return string
     */
    public static function buildHeader($uri = '/', $headers = array(), $bodyLength = 0, $method = '') {
        if ($method == '') {
            $method = $bodyLength==0 ? 'GET' : 'POST';
        }

        $sendHeader = "$method $uri HTTP/1.1\r\n";

        $defaultHeaders = array(
            'Connection'=>'keep-alive',
            'User-Agent'=>'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/43.0.2357.134 Safari/537.36',
        );
        if ($bodyLength > 0) {
            $defaultHeaders['Content-Length'] = $bodyLength;
            $defaultHeaders['Content-Type'] = 'application/x-www-form-urlencoded; charset=utf-8';
        }

        $headers = array_merge($defaultHeaders, $headers);
        foreach($headers as $k=>$v) {
            $sendHeader .= "{$k}: {$v}\r\n";
        }

        return $sendHeader."\r\n";
    }

    /**
     * @return string
     */
    protected function readHeader() {
        $header = '';
        do {
            $line = $this->tcp->fgets(8192);
            $header .= $line;
            if ($line == "\r\n") {
                break;
            }
            if ($this->tcp->getErrno() != 0) {
                break;
            }
        } while (!$this->tcp->feof());
        return $header;
    }

    protected function readChunkedBody() {
        $body = '';
        do {
            $_chunk_size = intval(hexdec($this->tcp->fgets(8192)));
            if ($this->tcp->getErrno() != 0) {
                break;
            }
            if ($_chunk_size > 0) {
                $body .= $this->tcp->recv($_chunk_size);
                if ($this->tcp->getErrno() != 0) {
                    break;
                }
            }
            $this->tcp->recv(2); // skip \r\n
            if ($this->tcp->getErrno() != 0) {
                break;
            }
            if ($_chunk_size < 1) {
                break;
            }
        } while (!$this->tcp->feof());
        return $body;
    }

    /**
     * @return SimpleNet_Tcp
     */
    public function getTcp() {
        return $this->tcp;
    }

    /**
     * @param SimpleNet_Tcp $tcp
     * @return $this
     */
    public function setTcp($tcp) {
        $this->tcp = $tcp;
        return $this;
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
     * @return string
     */
    public function getLastSend() {
        return $this->lastSendHeader.$this->lastSendBody;
    }

    public function getLastSendHeader() {
        return $this->lastSendHeader;
    }

    public function getLastSendBody() {
        return $this->lastSendBody;
    }

    /**
     * @return string
     */
    public function getLastRecv() {
        return $this->lastRecvHeader.$this->lastRecvBody;
    }

    /**
     * @return string
     */
    public function getLastRecvHeader() {
        return $this->lastRecvHeader;
    }

    /**
     * @return string
     */
    public function getLastRecvBody() {
        return $this->lastRecvBody;
    }

    /**
     * @return int
     */
    public function getStatusCode() {
        return $this->statusCode;
    }

    /**
     * @return string
     */
    public function getStatusText() {
        return $this->statusText;
    }

}