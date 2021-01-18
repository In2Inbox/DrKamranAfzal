<?php

class LogFileClass
{
	
	private $tZone='';
	private $buffered=true;
	private $logFileName='';
	private $fileHandle=null;
	private $mode='a+';
	private $queue=array();
	private $bufferLineCount=0;
	
	function fDate(){
		return date('m/d/Y h:i:s a', time());
	}
	
	function __construct($lfName='logfile.log', $mode='w+', $buffered=true)
	{
		if ($lfName==='') $this->logFileName='logfile.log';
		if ($mode==='') $this->mode='w+';
		$this->buffered=$buffered;
		$this->logFileName=$lfName;
		$this->mode=$mode;
		$this->fileHandle=fopen($this->logFileName, $this->mode);
		
	}
	
	private function openLog() {
		$this->closeLog();
		$this->fileHandle=fopen($this->logFileName, $this->mode);
	}
	
	function setMode($mode) {
		$this->mode=$mode;
		$this->openLog();
	}
	
	function __destruct()
	{
		$this->closeLog();
	}
	
	private function dumpBuffer() {
		foreach ($this->queue as $q) {
			fputs($this->fileHandle, $q);
		}
		$this->bufferLineCount=0;
	}
	
	function closeLog() {
		if ($this->fileHandle!=null)
		{
			if ($this->buffered) $this->dumpBuffer();
			fflush($this->fileHandle);
			fclose($this->fileHandle);
		}
	}
	
	function setTimeZone($tz) {
		if (date_default_timezone_set($tz)) {
			$this->tZone=$tz;
			return true;
		} else {
			return false;
		}
	}
	
	function linesInBuffer() {
		return $this->bufferLineCount;
	}
	
	function getHandle() {
		return $this->fileHandle;
	}
	
	function getLogFileName() {
		return $this->logFileName;
	}
	
	function getLogFileMode() {
		return $this->mode;
	}
	
	function getBuffered() {
		return $this->buffered;
	}
	
	function getLogTimeZone() {
		return $this->tZone;
	}
	
	function buffer() {
		$this->buffered=true;
	}
	
	function unbuffer() {
		$this->dumpBuffer();
		$this->buffered=false;
	}
	
	function lfWrite($st) {
		$st=$this->fDate().' - '.$st;
		if ($this->buffered) {
			$this->bufferLineCount++;
			$this->queue[$this->bufferLineCount]=$st;
		} else {
			fwrite($this->fileHandle, $st);
		}
	}
	
	function lfWriteLn($st) {
		$st=$this->fDate().' - '.$st.chr(10);
		if ($this->buffered) {
			$this->bufferLineCount++;
			$this->queue[$this->bufferLineCount]=$st;
		} else {
			fwrite($this->fileHandle, $st);
		}
	}
	
}