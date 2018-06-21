<?php
namespace Notify\Common;

use Notify\Config;
use Notify\Common\ServerException;

class Db {
	private $pdo = null;

	public function __construct(\PDO $pdo = null) {
		if ($pdo) {
			$this->pdo = $pdo;
		}
	}

	public function getPdo() {
		if ($this->pdo === null) {
			$dsn = sprintf('pgsql:host=%s;dbname=%s', Config::DbHost, Config::DbName);
			try {
				$this->pdo = new \PDO($dsn, Config::DbUser, Config::DbPassword, Config::DbOptions);
				$this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
			}
			catch (\PDOException $e) {
				throw new ServerException('Database error', $e->getMessage());
			}
		}
		return $this->pdo;
	}

	public function executeStatement(\PDOStatement $statement, $params) {
		try {
			$statement->execute($params);
		}
		catch (\PDOException $e) {
			throw new ServerException('Database error', $statement->errorInfo()[2]);
		}
	}

	public function query(string $query) {
		try {
			$this->getPdo()->query($query);
		}
		catch (\PDOException $e) {
			throw new ServerException('Database error', $this->getPdo()->errorInfo()[2]);
		}
	}
}
