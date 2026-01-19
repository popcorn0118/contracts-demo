<?php
/**
 * A thin wrapper for the PDO library.
 */
class Wslm_PdoDatabase extends Wslm_Database {
	private $pdo;

	function __construct($host, $dbname, $username, $password) {
		$dsn = 'mysql:dbname=' . $dbname . ';host=' . $host;
		$this->pdo = new PDO($dsn, $username, $password);
		//Use UTF-8 (utf8mb4) for everything.
		$this->pdo->query('SET NAMES \'utf8mb4\'');
	}

	public function getResults($query, $parameters = array()) {
		$statement = $this->pdo->prepare($query);
		$statement->execute($parameters);
		return $statement->fetchAll(PDO::FETCH_ASSOC);
	}

	public function query($query, $parameters = array()) {
		$statement = $this->pdo->prepare($query);
		$statement->execute($parameters);
		return $statement->rowCount();
	}
}
