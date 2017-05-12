<?php



class SimpleNet_Udp {

	/**
	 * @var self[]
	 */
	protected static $instances=array();

	protected $strHost='';
	protected $iPort=0;
	protected $iTimeout;
	protected $iLastErrNo = 0;
	protected $strLastErr = '';
	protected $fp;

	public static function getInstance($strHost, $iPort, $iTimeout = 1) {
		$key = static::getKey($strHost, $iPort, $iTimeout);
		if (!isset(static::$instances[$key])) {
			static::$instances[$key] = new static($strHost, $iPort, $iTimeout);
		}
		return static::$instances[$key];
	}

	protected static function getKey($strHost, $iPort, $iTimeout) {
		return $strHost.'-'.$iPort.'-'.$iTimeout;
	}

	protected function __construct($strHost, $iPort, $iTimeout = 1){
		$this->strHost = $strHost;
		$this->iPort = $iPort;
		$this->iTimeout = $iTimeout;
	}


	public function connect() {
		$this->fp = @fsockopen('udp://' . $this->strHost, $this->iPort, $this->iLastErrNo, $this->strLastErr, $this->iTimeout);
        $errData = error_get_last();
        if ($errData){
            $this->iLastErrNo = $errData['type'];
            $this->strLastErr = $errData['file'].':'.$errData['line'].', '.$errData['message'];
        }
		return is_resource($this->fp);
	}

    public function send($msg) {
        $length = strlen($msg);
        $wrote = 0;

        if (!@stream_set_timeout($this->fp, $this->iTimeout)) {
            $errData = error_get_last();
            $this->iLastErrNo = $errData['type'];
            $this->strLastErr = $errData['file'].':'.$errData['line'].', '.$errData['message'];
            return $wrote;
        }

        while ($wrote<$length) {
            $wrote += fwrite($this->fp, $msg, $length-$wrote);
            $msg = substr($msg, $wrote);
            $info = stream_get_meta_data($this->fp);
            if ($info['timed_out']) {
                $this->iLastErrNo = 20;
                $this->strLastErr = 'udp send timeout';
                break;
            }
        }

        return $wrote;
    }

	public function close() {
		if (is_resource($this->fp)) {
			fclose($this->fp);
			$this->fp = null;
			unset(static::$instances[static::getKey($this->strHost,$this->iPort,$this->iTimeout)]);
		}
	}

	public function isConnected(){
		return is_resource($this->fp);
	}
}