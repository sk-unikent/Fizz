<?php
/**
 * Fizz is a lightweight ORM
 *
 * @author Skylar Kelty <skylarkelty@gmail.com>
 */

namespace SkylarK\Fizz;

/**
 * The core of Fizz, all models should extend this
 */
abstract class Fizz
{
	/** Our PDO instance */
	private $_fizz_pdo;

	/**
	 * Initialize Fizz with valid database connection details
	 * 
	 * @param string $db_dsn A PDO connection string
	 * @param string $table The name of the table this model relates too
	 */
	public function __construct($table = NULL) {
		$this->_fizz_pdo = FizzConfig::getDB();
		if (!$this->_fizz_pdo) {
			throw new Exceptions\FizzDatabaseConnectionException("Could not connect to Database");
		}
	}

	/**
	 * Returns the table name
	 */
	public static function tablename() {
		return get_called_class();
	}

	/**
	 * Shortcut for our common PDO execution method
	 */
	private function _fizz_execute($query, $values) {
		if ($query->execute($values)) {
			return true;
		}
		return $query->errorInfo();
	}

	/**
	 * Return a list of fields we know about.
	 */
	public function _fizz_fields() {
		$fields = array();
		foreach (get_object_vars($this) as $key => $val) {
			if (is_string($key) && strpos($key, "_") !== 0) {
				$fields[] = $key;
			}
		}
		return $fields;
	}

	/**
	 * Insert into the database
	 */
	public function create() {
		$fields = $this->_fizz_fields();

		$values = array();
		foreach ($fields as $field) {
			$values[":" . $field] = $this->$field;
		}

		$sql = "INSERT INTO `".self::tablename()."` (`".implode("`,`", $fields)."`) VALUES (:".implode(",:", $fields).")";
		$q = $this->_fizz_pdo->prepare($sql);
		return $this->_fizz_execute($q, $values);
	}

	/**
	 * Update our record in the database
	 *
	 * @param array $new_data New data. Must be a multidimensional array(column=>value)
	 */
	public function update($new_data) {
		$fields = $this->_fizz_fields();
		$values = array();

		// Add in the sets
		$sets = array();
		foreach ($new_data as $key => $value) {
			$sets[] = "`" . mysql_real_escape_string($key) . "`=:" . $key;
			$values[':' . $key] = $value;
		}

		// Find the wheres
		$wheres = array();
		foreach ($fields as $key) {
			$wheres[] = "`" . mysql_real_escape_string($key) . "`=:FZCURRENT" . $key . " ";
			$values[":FZCURRENT" . $key] = $this->$key;
		}

		// Prepare the query
		$sql = "UPDATE `".self::tablename()."` SET " . implode(",", $sets) . " WHERE " . implode(" AND ", $wheres);

		$q = $this->_fizz_pdo->prepare($sql);
		$result = $this->_fizz_execute($q, $values);

		// Update class vars if we updated DB
		if ($result === true) {
			foreach ($new_data as $key => $value) {
				$this->$key = $value;
			}
		}

		return $result;
	}

	/**
	 * Returns an array of all results
	 */
	public static function all() {
		$pdo = FizzConfig::getDB();
		$sql = "SELECT * FROM " . self::tablename();
		$result = $pdo->query($sql);
		return $result->fetchAll(\PDO::FETCH_CLASS, get_called_class());
	}

	/**
	 * Truncate the table
	 * Use with caution!
	 */
	public function truncate() {
		$sql = "TRUNCATE `" . self::tablename() . "`";
		$q = $this->_fizz_pdo->prepare($sql);
		return $this->_fizz_execute($q, array());
	}
}