<?

/**
 * Server side call fulfillment script.
 * 
 * @package	isc-dhcp-configurator
 * @author	SBF
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
		1002	=> 'Referenced configuration file does not exist'
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
			PRIMARY KEY (id))
		");
		
		$this->db->query("
			CREATE TABLE reservations
			(id INTEGER NOT NULL,
			file_id INTEGER NOT NULL,
			mac_address VARCHAR(32) NOT NULL,
			ip_address VARCHAR(64) NOT NULL,
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
		
		$statement = $this->db->prepare("INSERT INTO files (id, label, created, updated) VALUES (:id, :label, :created, :updated)");
		$statement->bindValue(':id', $id);
		$statement->bindValue(':label', $request->label);
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
		
		return false;
		
	}
	
}