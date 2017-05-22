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

    protected $error = '';

    protected $sendData = '';
    protected $recvHeader = '';
    protected $recvBody = '';

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
        if ($this->recvHeader != '') {
            preg_match_all('/Set\-Cookie:\s*([^=]+)=([^;]+);(?:\s*expires=([^;]+);)?/is', $this->recvHeader, $m);
            foreach ($m[1] as $k=>$v) {
                $endtime = 2145891661;
                if ($m[3][$k] != '') {
                    $endtime = strtotime($m[3][$k]);
                }
                $this->cookies[$v] = $endtime.$m[2][$k];
            }
        }
    }

    /**
     * @param string $uri
     * @param array $headers
     * @param string|array $data
     * @param string $method
     * @return bool
     */
    public function request($uri = '/', $headers = array(), $data = '', $method = '') {
        $_headers = array();
        foreach($headers as $k=>$v) {
            $_headers[ucwords($k, '-')] = $v;
        }
        unset($headers);

        if ($this->enableCookie) {
            $this->checkCookie();
            foreach ($this->cookies as $key => $val) {
                if (!isset($_headers['Cookie'])) {
                    $_headers['Cookie'] = '';
                }
                if ('' != $_headers['Cookie']) {
                    $_headers['Cookie'] .= '; ';
                }
                $_headers['Cookie'] .= $key.'='.substr($val, 10);
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

        if (!isset($_headers['Host'])) {
            $_headers['Host'] = preg_replace('#\w+://#', '', $this->getTcp()->getHost());
        }

        $sendHeader = self::buildHeader($uri, $_headers, strlen($sendBody), $method);
        unset($_headers);

        if (!$this->requestRaw($sendHeader.$sendBody)) {
            return false;
        }

        if ($this->tcp->feof() || preg_match('/Connection:\s*close/i', $this->recvHeader)) {
            $this->tcp->close();
        }

        if (preg_match('/Content-Encoding:\s*(gzip|deflate)/i', $this->recvHeader, $m)) {
            switch ($m[1]) {
                case 'gzip':
                    $this->recvBody = gzdecode($this->recvBody);
                    break;
                case 'deflate':
                    $this->recvBody = gzinflate($this->recvBody);
                    break;
                default:
                    $this->error = 'unknown Content-Encoding: [' . $m[1] . ']';
                    return false;
            }
        }

        $this->statusCode = 0;
        $this->statusText = '';
        if (preg_match('/^HTTP\/[^\s]+\s+(\d+)\s+([^\r\n]+)\r\n/i', $this->recvHeader, $m)) {
            $this->statusCode = (int)$m[1];
            $this->statusText = trim($m[2]);
        }

        return true;
    }

    /**
     * @param string $msg
     * @return bool
     */
    public function requestRaw($msg) {
        $this->error = '';

        if ($this->tcp->isClose()) {
            if (!$this->tcp->connect()) {
                $this->error = $this->tcp->getError();
                return false;
            }
        }

        if (!$this->tcp->send($msg)) {
            $this->error = $this->tcp->getError();
            return false;
        }

        $this->sendData = $this->tcp->getSendData();

        if (!$this->readHeader()) {
            return false;
        }

        if (preg_match('/Content-Length:\s*(\d+)/i', $this->recvHeader,$m)){
            if (!$this->tcp->recv(intval($m[1]))) {
                $this->error = $this->tcp->getError();
                return false;
            }
            $this->recvBody = $this->tcp->getRecvData();
        } elseif (preg_match('/Transfer-Encoding:\s*chunked/i', $this->recvHeader)) {
            return $this->readChunkedBody();
        } else {
            $this->error = 'bad response [' . $this->recvHeader . ']';
            $this->tcp->close(); // 响应协议错误
            return false;
        }

        return true;
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
     * @param int $maxLine
     * @return bool
     */
    protected function readHeader($maxLine = 8192) {
        $this->recvHeader = '';
        do {
            if (!$this->tcp->fgets($maxLine)) {
                $this->error = $this->tcp->getError();
                return false;
            }
            $this->recvHeader .= $this->tcp->getRecvData();
            if ($this->tcp->getRecvData() == "\r\n") {
                break;
            }
        } while (!$this->tcp->feof());
        return true;
    }

    /**
     * @return bool
     */
    protected function readChunkedBody() {
        $this->recvBody = '';
        do {
            if (!$this->tcp->fgets(512)) {
                $this->error = $this->tcp->getError();
                return false;
            }
            $_chunk_size = intval(hexdec($this->tcp->getRecvData()));
            if ($_chunk_size > 0) {
                if (!$this->tcp->recv($_chunk_size)) {
                    $this->error = $this->tcp->getError();
                    return false;
                }
                $this->recvBody .= $this->tcp->getRecvData();
            }
            if (!$this->tcp->recv(2)) { // skip \r\n
                $this->error = $this->tcp->getError();
                return false;
            }
            if ($_chunk_size < 1) {
                break;
            }
        } while (!$this->tcp->feof());
        return true;
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
     * @return string
     */
    public function getError() {
        return $this->error;
    }

    /**
     * @return string
     */
    public function getSend() {
        return $this->sendData;
    }

    /**
     * @return string
     */
    public function getRecv() {
        return $this->recvHeader.$this->recvBody;
    }

    /**
     * @return string
     */
    public function getRecvHeader() {
        return $this->recvHeader;
    }

    /**
     * @return string
     */
    public function getRecvBody() {
        return $this->recvBody;
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
