<?php

/**
 * Server side call fulfillment script.
 * 
 * @package	isc-dhcp-configurator
 * @author	SBF
 * @version	1.00
 */

// setup and execute
$api = new API();

class API {
	
	/**
	 * Error codes.
	 * 
	 * @var array 
	 */
	private $errorCodes = array(
		1000	=> 'Method does not exist',
		1001	=> 'Database file could not be created',
		1002	=> 'Referenced configuration file does not exist',
		1003	=> 'SQLite3 is not installed on your web server'
	);
	
	/**
	 * Methods which cannot be called by clients
	 * 
	 * @var array
	 */
	private $forbiddenMethods = array(
		'createTables', 'sendError'
	);
	
	/**
	 * Raise errors in this property using a code.
	 * 
	 * @var string
	 */
	private $error;
	
	private $localurlpath = '/isc-dhcp-configurator/';
	/**
	 * Database handle.
	 * 
	 * @var SQLiteDatabase
	 */
	private $db;
	
	/**
	 * Construct.
	 */
	public function __construct() {
		
		// check SQLite3 is installed
		if (!class_exists('SQLite3')) {
			header('HTTP/1.1 400', true, 400);
			$this->sendError(1003);
			exit;
		}
		
		// decode request
		$request = json_decode(file_get_contents('php://input'));
		
		// check method called exists
		if (empty($request->method) || !method_exists($this, $request->method) || in_array($request->method, $this->forbiddenMethods)) {
			header('HTTP/1.1 400', true, 400);
			$this->sendError(1000);
			return;
		}
		
		// set up database
		if (is_file('data.sqlite')) {
			
			// open existing file
			$this->db = new SQLite3('data.sqlite');
			
		} else {
			
			// try to create new database file
			try {
				$this->db = new SQLite3('data.sqlite');
				$this->createTables();
			} catch (Exception $e) {}
			
		}
		
		// call method
		$method = $request->method;
		$response = $this->$method($request);
		
		// send status and response
		if (!empty($this->error)) {
			header('HTTP/1.1 400', true, 400);
			$this->sendError($this->error);
		} else {
			header('HTTP/1.1 200 OK', true, 200);
			print json_encode($response);
		}
		
		// close database
		if ($this->db) $this->db->close();
		
	}
	
	/**
	 * Send error code and text.
	 * 
	 * @param integer $code
	 */
	private function sendError($code) {
		
		print json_encode(array(
			'code'	=> $code,
			'error'	=> $this->errorCodes[$code]
		));
		
	}
	
	/**
	 * Create database tables in new database.
	 * 
	 * @return void
	 */
	private function createTables() {
		
		$this->db->query("
			CREATE TABLE files
			(id INTEGER NOT NULL,
			label VARCHAR(255) NOT NULL,
			subnet VARCHAR(16) NOT NULL,
			netmask VARCHAR(16) NOT NULL,
			created INTEGER NOT NULL,
			updated INTEGER NOT NULL,
			PRIMARY KEY (id))
		");
		
		$this->db->query("
			CREATE TABLE parameters
			(id INTEGER NOT NULL,
			file_id INTEGER NOT NULL,
			param_key VARCHAR(255) NOT NULL,
			param_val TEXT(25) NOT NULL,
			notes VARCHAR(255) NOT NULL,
			PRIMARY KEY (id))
		");
		
		$this->db->query("
			CREATE TABLE reservations
			(id INTEGER NOT NULL,
			file_id INTEGER NOT NULL,
			mac_address VARCHAR(32) NOT NULL,
			ip_address VARCHAR(64) NULL,
			filename VARCHAR(255) NULL,
			label VARCHAR(255) NOT NULL,
			PRIMARY KEY (id))
		");
		
	}
	
	/**
	 * Empty all database tables.
	 * 
	 * @return void
	 */
	private function emptyTables() {
		foreach (array('files', 'parameters', 'reservations') as $table) {
			$this->db->query("DELETE FROM {$table};");
		}
	}
	
	/**
	 * Get next ID in a database table.
	 * 
	 * @param string $table
	 */
	private function getNextID($table) {
		$result = $this->db->query("SELECT COALESCE(MAX(id),0) AS id FROM {$table}");
		while ($row = $result->fetchArray()) return $row['id']+1;
	}
	
	/**
	 * Check that databse file exists
	 * 
	 * @param array $post POST parameters
	 */
	private function checkdb($post) {
		if (!$this->db) $this->error = 1001;
	}
	
	/**
	 * Create new configuration file.
	 * 
	 * @param array $request
	 * 
	 * @return array
	 */
	private function createFile($request) {
		
		$id = $this->getNextID('files');
		
		$statement = $this->db->prepare("INSERT INTO files (id, label, subnet, netmask, created, updated) VALUES (:id, :label, :subnet, :netmask, :created, :updated)");
		$statement->bindValue(':id', $id);
		$statement->bindValue(':label', $request->label);
		$statement->bindValue(':subnet', $request->subnet);
		$statement->bindValue(':netmask', $request->netmask);
		$statement->bindValue(':created', time());
		$statement->bindValue(':updated', time());
		$statement->execute();
		
		return array('id' => $id);
		
	}
	
	/**
	 * List all existing files.
	 * 
	 * @param array $request
	 */
	private function listFiles($request) {
		
		$list = array();
		
		$result = $this->db->query("SELECT * FROM files ORDER BY label ASC, updated DESC, created DESC");
		while ($row = $result->fetchArray(SQLITE3_ASSOC)) $list[] = $row;
		
		return array('list' => $list);
		
	}
	
	/**
	 * Delete a file and all its linked data
	 * 
	 * @param array $request
	 */
	private function deleteFile($request) {
		
		$statement = $this->db->prepare("DELETE FROM files WHERE id = :id");
		$statement->bindValue(':id', $request->id);
		$statement->execute();
		
		$statement = $this->db->prepare("DELETE FROM parameters WHERE file_id = :id");
		$statement->bindValue(':id', $request->id);
		$statement->execute();
		
		$statement = $this->db->prepare("DELETE FROM reservations WHERE file_id = :id");
		$statement->bindValue(':id', $request->id);
		$statement->execute();
		
		return array('id' => $request->id);
		
	}
	
	/**
	 * Loads linked data for a file.
	 * 
	 * @param array $request
	 */
	private function loadFile($request) {
		
		if (!$this->fileRecordExists($request->id)) {
			$this->error = 1002;
			return;
		}
		
		$parameters = $reservations = array();
		
		$result = $this->db->query("SELECT * FROM parameters WHERE file_id = {$request->id} ORDER BY id");
		while ($row = $result->fetchArray(SQLITE3_ASSOC)) $parameters[] = $row;
		
		$result = $this->db->query("SELECT * FROM reservations WHERE file_id = {$request->id} ORDER BY id");
		while ($row = $result->fetchArray(SQLITE3_ASSOC)) $reservations[] = $row;
		
		return array(
			'id'		=> $request->id,
			'parameters'	=> $parameters,
			'reservations'	=> $reservations
		);
		
	}
	
	/**
	 * Determines if a file record with a given ID exists.
	 * 
	 * @param integer $id
	 * 
	 * @return boolean
	 */
	private function fileRecordExists($id) {
		
		$result = $this->db->query("SELECT * FROM files WHERE id = {$id} ORDER BY id");
		while ($row = $result->fetchArray(SQLITE3_ASSOC)) if ($row['id'] == $id) return true;
		
		return false;
		
	}
	
	/**
	 * Saves a file by replacing all records with submitted set.
	 * 
	 * @param array $request
	 */
	private function saveFile($request) {
		
		// set update time
		$updated = time();
		
		// update main file record
		$statement = $this->db->prepare("UPDATE files SET label= :label, subnet = :subnet, netmask = :netmask, updated = :updated WHERE id = :id");
		$statement->bindValue(':label', $request->file->label);
		$statement->bindValue(':subnet', $request->file->subnet);
		$statement->bindValue(':netmask', $request->file->netmask);
		$statement->bindValue(':updated', $updated);
		$statement->bindValue(':id', $request->file->id);
		$statement->execute();
		
		// remove existing parameters
		$statement = $this->db->prepare("DELETE FROM parameters WHERE file_id = :id");
		$statement->bindValue(':id', $request->file->id);
		$statement->execute();
		
		// remove existing reservations
		$statement = $this->db->prepare("DELETE FROM reservations WHERE file_id = :id");
		$statement->bindValue(':id', $request->file->id);
		$statement->execute();
		
		// replace parameters
		foreach ($request->parameters as $parameter) {
			$statement = $this->db->prepare("INSERT INTO parameters (id, file_id, param_key, param_val, notes) VALUES (:id, :file_id, :key, :val, :notes)");
			$statement->bindValue(':id', $this->getNextID('parameters'));
			$statement->bindValue(':file_id', $request->file->id);
			$statement->bindValue(':key', $parameter->param_key);
			$statement->bindValue(':val', $parameter->param_val);
			$statement->bindValue(':notes', $parameter->notes);
			$statement->execute();
		}
		
		// replace reservations
		foreach ($request->reservations as $reservation) {
			$statement = $this->db->prepare("INSERT INTO reservations (id, file_id, label, mac_address, ip_address, bootfile ) VALUES (:id, :file_id, :label, :mac, :ip, :filename)");
			$statement->bindValue(':id', $this->getNextID('reservations'));
			$statement->bindValue(':file_id', $request->file->id);
			$statement->bindValue(':label', $reservation->label);
			$statement->bindValue(':mac', $reservation->mac_address);
			$statement->bindValue(':ip', $reservation->ip_address);
			$statement->bindValue(':filename', $reservation->bootfile);
			$statement->execute();
		}
		
		//print_r($request);
		$configfile = 'file.txt';
		$fh = fopen($configfile, "w");
		fwrite($fh, serialize($request));
		fclose($fh);
		
		return array(
			'id'		=> $request->file->id,
			'updated'	=> $updated
		);
		
	}
	
	/**
	 * Get client ip address
	 * 
	 * @param null
	 * 
	 * @return $ip
	 */
	private function getClientIp() {
		$unknown = 'unknown';
		if ( isset($_SERVER['HTTP_X_FORWARDED_FOR'])
			&& $_SERVER['HTTP_X_FORWARDED_FOR']
			&& strcasecmp($_SERVER['HTTP_X_FORWARDED_FOR'],
			$unknown) ) {
				$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
			} elseif ( isset($_SERVER['REMOTE_ADDR'])
			&& $_SERVER['REMOTE_ADDR'] &&
			strcasecmp($_SERVER['REMOTE_ADDR'], $unknown) ) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		return $ip;
	}
	
	/**
	 * Save config to a conf file, for dhcpd.conf include.
	 * 
	 * @param array $request
	 */
	private function genConfigFile($request) {
		
		// set update time
		//$updated = date(‘Y-m-d H:i:s’);
		$updated = time();
		$clientip = $this->getClientIp();
		$contentForWrite = "# Modified by " . $clientip . " @" . date('Y-m-d H:i:s') . "\n" . $request->content;
		
		//if (!$this->fileRecordExists($request->id)) {
		//	$this->error = 1002;
		//	return;
		//}
				
		#$configfile = 'dhcpd-include-ipv4.conf';
		$configfile = 'dhcpd-include-ipv4.conf';

		$fh = fopen($configfile, "w");
		//$status = fwrite($fh, $request->content);
		//$status = fwrite($fh, "# Modified by ".$clientip." @".$updated."\n".$request->content);
		$status = fwrite($fh, $contentForWrite);
		fclose($fh);
		
		return array(
			'status'		=> $status,
			'filename'	=>	$configfile,
			'updated'	=> $updated /*,
			'reservations'	=> $reservations */
		);
		
	}

	// 
	// Get CGI URI
	// 
	// @param string $operation
	// 
	// @return string full_uri
	// 
	private function getCgiUri($operation) {
		
		//$http = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? 'https://' : 'http://';
		//$port = $_SERVER['SERVER_PORT']==80 ? '' : ':'.$_SERVER["SERVER_PORT"];
		//$baseurl = $http.$_SERVER['SERVER_NAME'].$port.$_SERVER['DOCUMENT_ROOT'].'/cgi-bin/testenv.cgi?';
		//$baseurl = $http.$_SERVER['SERVER_NAME'].$this->localurlpath.'/cgi-bin/testenv.cgi?';
		$baseurl = 'http://127.0.0.1:808/cgibin/testenv.cgi?';
		if ($operation == 'test4' 
			|| $operation == 'test6' 
			|| $operation == 'status4' 
			|| $operation == 'status6' 
			|| $operation == 'restart4' 
			|| $operation == 'restart6' ) 
		{
			$url = $baseurl.$operation;
		}
		else 
		{
			$url = $baseurl.'no-op';
		}
		return $url;
	}

	// 
	// Check dhcp-config file.
	// 
	// @param $request ('4' or '6')
	// 
	private function checkConfigFile($request) {
	
		if ($request == 4 || $request == '4' || $request == 'ipv4' ) {
			$url = $this->getCgiUri('test4');
		}
		else if ($request == 6 || $request == '6' || $request == 'ipv6' ) {
			$url = $this->getCgiUri('test6');
		}
		
		// process url via http GET
		$re = file_get_contents($url);
		
		if ( FALSE == strpos($re, 'exiting', 0)) {
			//printf('ISC-DHCP config file test OK!');
			return TRUE;
		}
		else {
			//printf('ISC-DHCP config file test Fail!');
			return FALSE;
		}
	}
	
	// 
	// restart ipv4 dhcp server.
	// 
	// @param array $request
	// 
	private function restartDhcpServer4($request) {
		// set update time
		$updated = date('Y-m-d H:i:s');
		//$updated = time();

		$checkconf = $this->checkConfigFile('ipv4');

		if ($checkconf) {
			//config file check OK
			$url = $this->getCgiUri('restart4');
			$re = file_get_contents($url);
			
			$url = $this->getCgiUri('status4');
			$re = file_get_contents($url);
			
			if ( FALSE == strpos($re, 'running', 0)) {
				//printf('ISC-DHCP config file test Fail!');
				$message = 'Restart DHCPv4 Server Fail!';
				$status = FALSE;
			}
			else {
				//printf('ISC-DHCP config file test OK!');
				$message = 'New config is taking effect!';
				$status = TRUE;
			}
		}
		else {
			//config file check Fail
			$message = 'Check new config file Fail!';
			$status = FALSE;
		}
		
		return array(
			'status'	=> $status,
			'message'	=> $message,
			'updated'	=> $updated,
			'debug'	=> $re
		);
	}

}
