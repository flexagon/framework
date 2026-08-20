<?php
/**
 * Mssql
 *
 * Flexagon : PHP Development Framework
 *
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */
namespace _Flexagon\DB;

use _Flexagon\DB\Interfaces\RDB;
use _Flexagon\Libs\DataSourceManager;
use _Flexagon\Libs\SqlUtil;
use _Flexagon\Models\DataSourceModel;
use _Flexagon\Supporters\DBSupporter;
use PDO;
use PDOException;
use PDOStatement;

/**
 * Microsoft SQL Server driver.
 *
 * Mirrors Mysql: PDO underneath, connections pooled per data source id, and
 * every value bound as a parameter instead of being pasted into the SQL.
 *
 * Works with either PDO driver, preferring pdo_sqlsrv over pdo_dblib.
 */
class Mssql extends DBSupporter implements RDB {
	/**
	 * @var PDO[]
	 */
	private array $pdo = [];
	private ?PDOStatement $stmt = null;

	/**
	 * Prepared statements keyed by data source and SQL. See Mysql for why.
	 *
	 * @var PDOStatement[]
	 */
	private array $statementCache = [];
	private string $connectionEncoding = 'UTF-8';
	private ?string $error = null;
	private ?string $latestQuery = null;
	private ?array $latestParams = null;
	private ?DataSourceModel $dataSourceModel = null;

	/**
	 * Which PDO driver buildDsn() settled on: 'sqlsrv' or 'dblib'.
	 */
	private ?string $driverName = null;

	/**
	 * Data source ids already warned about, so the notice costs one log line
	 * per source rather than one per connect.
	 *
	 * @var array<string,bool>
	 */
	private static array $warnedUnicodeSourceIds = [];

	/**
	 * @return bool
	 */
	public function isConnected(): bool
	{
		if ( is_null($this->dataSourceModel) ) {
			return false;
		}
		return !empty($this->pdo[$this->dataSourceModel->getSourceId()]);
	}

	/**
	 * MySQL charset names travel in DataSourceModel, so fold the common ones
	 * onto what SQL Server expects.
	 *
	 * @param string|null $charset
	 * @return string
	 */
	private static function normalizeCharset(?string $charset): string
	{
		$charset = strtolower(trim((string)$charset));
		if ( $charset === '' || str_starts_with($charset, 'utf8') || $charset === 'utf-8' ) {
			return 'UTF-8';
		}
		return $charset;
	}

	/**
	 * @param DataSourceModel $dataSourceModel
	 * @return string|null
	 */
	private function buildDsn(DataSourceModel $dataSourceModel): ?string
	{
		$drivers = PDO::getAvailableDrivers();
		$host = $dataSourceModel->getHost();
		$port = $dataSourceModel->getPort();
		$name = $dataSourceModel->getDbName();

		if ( in_array('sqlsrv', $drivers, true) ) {
			$this->driverName = 'sqlsrv';
			$server = $port > 0 ? "{$host},{$port}" : $host;
			return sprintf('sqlsrv:Server=%s;Database=%s', $server, $name);
		}

		if ( in_array('dblib', $drivers, true) ) {
			$this->driverName = 'dblib';
			$server = $port > 0 ? "{$host}:{$port}" : $host;
			return sprintf('dblib:host=%s;dbname=%s;charset=%s', $server, $name, $this->connectionEncoding);
		}

		$this->driverName = null;
		return null;
	}

	/**
	 * Which PDO driver this connection went out on.
	 *
	 * @return string|null 'sqlsrv', 'dblib', or null before connect()
	 */
	public function getDriverName(): ?string
	{
		return $this->driverName;
	}

	/**
	 * Say once that this connection cannot carry Unicode into the database.
	 *
	 * pdo_dblib binds string parameters through Sybase DB-Library, which has no
	 * NVARCHAR parameter to bind to: the value is converted to the server's
	 * single byte codepage on the way out, so anything outside that codepage
	 * arrives as '?'. Reading is unaffected, and the loss is silent -- the
	 * INSERT succeeds and the row looks fine until someone reads the column.
	 *
	 * The framework's own non-prepared path writes N'...' literals, which do
	 * carry Unicode, so turning prepared statements off on this data source is
	 * a way out. It inlines values into the SQL text, so pdo_sqlsrv is the
	 * better answer where it can be installed.
	 *
	 * @param DataSourceModel $dataSourceModel
	 * @return void
	 */
	private function warnUnicodeLimitation(DataSourceModel $dataSourceModel): void
	{
		if ( !\_Global::$DB_WARN_DBLIB_UNICODE ) { return; }
		if ( $this->driverName !== 'dblib' ) { return; }
		if ( !$dataSourceModel->isUsePrepareStatement() ) { return; }

		$sourceId = (string)$dataSourceModel->getSourceId();
		if ( isset(self::$warnedUnicodeSourceIds[$sourceId]) ) { return; }
		self::$warnedUnicodeSourceIds[$sourceId] = true;

		error_log(sprintf(
			'[FLEXAGON] data source "%s" uses pdo_dblib with prepared statements: characters outside the server codepage are written to NVARCHAR columns as "?" without an error. '
			. 'Install pdo_sqlsrv, or call setUsePrepareStatement(false) on this data source to write N\'...\' literals instead. '
			. 'Set _Global::$DB_WARN_DBLIB_UNICODE = false to silence this.',
			$sourceId
		));
	}

	/**
	 * @param DataSourceModel $dataSourceModel
	 * @return bool
	 * @see \_Flexagon\DB\Interfaces\RDB::connect()
	 */
	public function connect(DataSourceModel $dataSourceModel): bool
	{
		$this->connectionEncoding = self::normalizeCharset($dataSourceModel->getCharset());
		$this->dataSourceModel = $dataSourceModel;

		if ( !empty($this->pdo[$dataSourceModel->getSourceId()]) ) {
			return true;
		}

		$dsn = $this->buildDsn($dataSourceModel);
		if ( is_null($dsn) ) {
			$this->error = 'No SQL Server PDO driver available (need pdo_sqlsrv or pdo_dblib)';
			return false;
		}

		$options = [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		];

		try {
			$this->pdo[$dataSourceModel->getSourceId()] = new PDO(
				$dsn,
				$dataSourceModel->getUsername(),
				$dataSourceModel->getPassword(),
				$options
			);
		} catch (PDOException $e) {
			$this->flushLatestQuery();
			$this->error = $e->getMessage();
			return false;
		}

		$this->warnUnicodeLimitation($dataSourceModel);

		return !empty($this->pdo[$dataSourceModel->getSourceId()]);
	}

	/**
	 * @return bool
	 * @see \_Flexagon\DB\Interfaces\RDB::disconnect()
	 */
	public function disconnect(): bool
	{
		if ( $this->isConnected() ) {
			$this->pdo[$this->dataSourceModel->getSourceId()] = null;
		}

		$this->statementCache = [];

		return true;
	}

	/**
	 * @param string $query
	 * @param bool $usePrepareStatement
	 * @return bool
	 */
	public function query(string $query, bool $usePrepareStatement = true): bool
	{
		if ( !$this->isConnected() ) {
			$this->error = "can't send query :: not connected DB";
			return false;
		}

		if ( empty($query) ) {
			return true;
		}

		$this->stmt = null;
		$this->latestQuery = $query;

		try {
			if ( $usePrepareStatement ) {
				$this->stmt = $this->prepareStatement($query);
			} else {
				$command = strtoupper(strtok(ltrim($query), " "));

				switch ($command) {
					case 'INSERT':
					case 'UPDATE':
					case 'DELETE':
					case 'MERGE':
					case 'TRUNCATE':
						$this->pdo[$this->dataSourceModel->getSourceId()]->exec($query);
						break;

					default:
						$this->stmt = $this->pdo[$this->dataSourceModel->getSourceId()]->query($query);
				}
			}
			return true;
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			return false;
		}
	}

	/**
	 * Prepare $query, reusing the statement when the same SQL comes round again.
	 *
	 * @param string $query
	 * @return PDOStatement
	 */
	private function prepareStatement(string $query): PDOStatement
	{
		$pdo = $this->pdo[$this->dataSourceModel->getSourceId()];
		$cacheSize = \_Global::$DB_STATEMENT_CACHE_SIZE;

		if ( $cacheSize <= 0 ) {
			return $pdo->prepare($query);
		}

		$cacheKey = $this->dataSourceModel->getSourceId() . '|' . $query;

		if ( isset($this->statementCache[$cacheKey]) ) {
			$statement = $this->statementCache[$cacheKey];
			$statement->closeCursor();
			return $statement;
		}

		$statement = $pdo->prepare($query);

		if ( count($this->statementCache) >= $cacheSize ) {
			array_shift($this->statementCache);
		}
		$this->statementCache[$cacheKey] = $statement;

		return $statement;
	}

	/**
	 * @param string|int $param
	 * @param mixed $value
	 * @param int $dataType
	 * @return void
	 * @see \_Flexagon\DB\Interfaces\RDB::bindParam()
	 */
	public function bindParam($param, $value, int $dataType = PDO::PARAM_STR): void
	{
		if ( !is_null($this->stmt) ) {
			$this->stmt->bindParam($param, $value, $dataType);
		}
	}

	/**
	 * @param array|null $params
	 * @return bool
	 * @see \_Flexagon\DB\Interfaces\RDB::executePrepare()
	 */
	public function executePrepare($params = null): bool
	{
		if ( !$this->isConnected() ) {
			$this->flushLatestQuery();
			$this->error = "Can't send query :: not connected DB";
			return false;
		}

		if ( $this->stmt == null ) {
			return false;
		}

		$this->latestParams = $params;

		$sqlParams = SqlUtil::convertArrayToSqlParams($params);

		try {
			return $this->stmt->execute($sqlParams);
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			return false;
		}
	}

	/**
	 * Query executing function.
	 *
	 * @param string $query
	 * @param array|null $params
	 * @param bool|null $usePrepareStatement
	 * @return bool
	 * @see \_Flexagon\DB\Interfaces\RDB::executeQuery()
	 */
	public function executeQuery(string $query, ?array $params = null, ?bool $usePrepareStatement = null): bool
	{
		if ( !$this->isConnected() ) {
			$this->flushLatestQuery();
			$this->error = "Can't send query :: not connected DB";
			return false;
		}

		if ( is_array($params) && empty($params) ) {
			$params = null;
		}

		if ( DataSourceManager::isTransaction() && !$this->dataSourceModel->isTransaction() ) {
			$this->startTransaction();
			$this->dataSourceModel->setTransaction(true);
		}

		$this->flushLatestQuery();

		if ( $usePrepareStatement == null ) {
			$usePrepareStatement = $this->dataSourceModel->isUsePrepareStatement();
		}

		if ( $usePrepareStatement ) {
			list($query, $params) = SqlUtil::expandQueryParams($query, $params);

			if ( !$this->query($query, true) ) {
				$result = false;
			} else {
				$result = $this->executePrepare($params);
			}
		} else {
			$query = $this->combineQueryParams($query, $params);
			$result = $this->query($query, false);
		}

		if ( \_Global::$DEBUG_QUERY_BREAKPOINT ) { $this->debugQueryAfter(); }

		return $result;
	}

	public function flushLatestQuery(): void
	{
		$this->latestQuery = null;
		$this->latestParams = null;
	}

	/**
	 * @return string|null
	 */
	public function debugQuery(): ?string
	{
		return $this->combineQueryParams($this->latestQuery, $this->latestParams);
	}

	/**
	 * Inline parameters into a query using T-SQL literal syntax.
	 *
	 * SqlUtil::combineQueryParams() speaks MySQL — double-quoted strings and
	 * backslash escapes — which SQL Server reads as identifiers, so this renders
	 * N'...' literals with doubled quotes instead.
	 *
	 * @param string|null $query
	 * @param array|null $params
	 * @return string
	 */
	private function combineQueryParams(?string $query, ?array $params = null): string
	{
		if ( is_null($query) ) {
			return '';
		}
		if ( empty($params) ) {
			return $query;
		}

		return preg_replace_callback('/:([_a-zA-Z][_a-zA-Z0-9]*)/', function ($matches) use ($params) {
			$key = $matches[1];
			if ( !array_key_exists($key, $params) ) {
				return $matches[0];
			}

			$value = $params[$key];
			if ( $value instanceof \UnitEnum ) {
				$value = $value->value ?? $value->name;
			}

			switch (gettype($value)) {
				case 'integer':
				case 'double':
					return (string)$value;
				case 'boolean':
					 
					return $value ? '1' : '0';
				case 'NULL':
					return 'NULL';
				case 'string':
					 
					return "N'" . (string)$this->escapeString($value) . "'";
				default:
					return 'NULL';
			}
		}, $query);
	}

	/**
	 * Breakpoint anchor. Called right after every query when
	 * _Global::$DEBUG_QUERY_BREAKPOINT is on, so that a debugger can stop here
	 * with the executed query still available via debugQuery().
	 *
	 * @return void
	 */
	public function debugQueryAfter(): void
	{
	}

	public function debugDumpParams(): void
	{
		if ( !is_null($this->stmt) ) {
			$this->stmt->debugDumpParams();
		}
	}

	/**
	 * @return int|null
	 * @see \_Flexagon\DB\Interfaces\RDB::getTotalNumRows()
	 */
	public function getTotalNumRows(): ?int
	{
		return $this->getAffectedRows();
	}

	/**
	 * @return int|null
	 * @see \_Flexagon\DB\Interfaces\RDB::getResultNumRows()
	 */
	public function getResultNumRows(): ?int
	{
		return $this->getAffectedRows();
	}

	/**
	 * @param int $fetchMode PDO::FETCH_* constant
	 * @return array|null
	 * @see \_Flexagon\DB\Interfaces\RDB::getResultAsArray()
	 */
	public function getResultAsArray(int $fetchMode = PDO::FETCH_BOTH): ?array
	{
		if ( empty($this->stmt) ) {
			return null;
		}

		try {
			$returnFetchData = $this->stmt->fetch($fetchMode);
			if ( $returnFetchData === false ) {
				return null;
			}
			return $returnFetchData;
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			return null;
		}
	}

	/**
	 * @param int $fetchStyle PDO::FETCH_* constant
	 * @return array|null
	 * @see \_Flexagon\DB\Interfaces\RDB::getAllResultAsArray()
	 */
	public function getAllResultAsArray(int $fetchStyle = PDO::FETCH_BOTH): ?array
	{
		if ( empty($this->stmt) ) {
			return [];
		}

		try {
			$returnFetchData = $this->stmt->fetchAll($fetchStyle);
			if ( $returnFetchData === false ) {
				return null;
			}
			return $returnFetchData;
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			return null;
		}
	}

	/**
	 * @param int $fetchStyle PDO::FETCH_* constant
	 * @return mixed
	 * @see \_Flexagon\DB\Interfaces\RDB::getResult()
	 */
	public function getResult(int $fetchStyle = PDO::FETCH_BOTH): mixed
	{
		$resultArray = self::getResultAsArray($fetchStyle);
		if ( !is_array($resultArray) || count($resultArray) <= 0 ) {
			return null;
		}
		return array_shift($resultArray);
	}

	/**
	 * @return bool
	 * @see \_Flexagon\DB\Interfaces\RDB::startTransaction()
	 */
	public function startTransaction(): bool
	{
		if ( !$this->isConnected() ) {
			return false;
		}

		$pdo = $this->pdo[$this->dataSourceModel->getSourceId()];
		if ( $pdo->inTransaction() ) {
			return true;
		}

		$pdo->beginTransaction();
		return true;
	}

	/**
	 * @return bool
	 * @see \_Flexagon\DB\Interfaces\RDB::rollback()
	 */
	public function rollback(): bool
	{
		if ( !$this->isConnected() ) {
			return false;
		}

		$this->dataSourceModel->setTransaction(false);

		$pdo = $this->pdo[$this->dataSourceModel->getSourceId()];
		if ( !$pdo->inTransaction() ) {
			return false;
		}

		$pdo->rollBack();
		return true;
	}

	/**
	 * @return bool
	 * @see \_Flexagon\DB\Interfaces\RDB::commit()
	 */
	public function commit(): bool
	{
		if ( !$this->isConnected() ) {
			return false;
		}

		$this->dataSourceModel->setTransaction(false);

		$pdo = $this->pdo[$this->dataSourceModel->getSourceId()];
		if ( !$pdo->inTransaction() ) {
			return false;
		}

		$pdo->commit();
		return true;
	}

	/**
	 * @param $object
	 * @return array
	 * @see \_Flexagon\DB\Interfaces\RDB::getAllResultAsObject()
	 */
	public function getAllResultAsObject($object): array
	{
		return parent::getAllResultAsObjectFromArray($object, $this->getAllResultAsArray());
	}

	/**
	 * @param $object
	 * @return object|null
	 * @see \_Flexagon\DB\Interfaces\RDB::getResultAsObject()
	 */
	public function getResultAsObject($object): ?object
	{
		return parent::getResultAsObject($object);
	}

	/**
	 * @return string|null
	 */
	public function getError(): ?string
	{
		return $this->error;
	}

	/**
	 * pdo_dblib has no lastInsertId(), so fall back to SCOPE_IDENTITY().
	 *
	 * @return string|null
	 */
	public function getInsertId(): ?string
	{
		if ( !$this->isConnected() ) {
			return null;
		}

		$pdo = $this->pdo[$this->dataSourceModel->getSourceId()];

		try {
			$insertId = $pdo->lastInsertId();
			if ( !empty($insertId) ) {
				return strval($insertId);
			}
		} catch (PDOException $e) {
		}

		try {
			$statement = $pdo->query('SELECT CONVERT(VARCHAR(64), SCOPE_IDENTITY()) AS id');
			if ( $statement === false ) {
				return null;
			}
			$row = $statement->fetch(PDO::FETCH_ASSOC);
			if ( empty($row['id']) ) {
				return null;
			}
			return strval($row['id']);
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			return null;
		}
	}

	/**
	 * @return int|null
	 */
	public function getAffectedRows(): ?int
	{
		if ( empty($this->stmt) ) {
			return null;
		}

		try {
			return $this->stmt->rowCount();
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			return null;
		}
	}

	/**
	 * T-SQL escapes a single quote by doubling it; there is no backslash
	 * escape. Control characters are dropped rather than encoded.
	 *
	 * Prefer bound parameters — this exists for the rare identifier or literal
	 * that cannot be bound.
	 *
	 * @param string|null $string
	 * @return string|null
	 * @see \_Flexagon\DB\Interfaces\RDB::escapeString()
	 */
	public function escapeString(?string $string): ?string
	{
		if ( is_null($string) ) {
			return null;
		}

		$string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $string);
		if ( is_null($string) ) {
			return null;
		}

		return str_replace("'", "''", $string);
	}

	/**
	 * SQL Server has no ATTR_AUTOCOMMIT; kept for interface symmetry with
	 * Mysql and turned into a no-op when the driver refuses it.
	 *
	 * @param bool $status
	 * @return void
	 */
	public function setAutoCommit(bool $status): void
	{
		if ( !$this->isConnected() ) {
			return;
		}

		try {
			$this->pdo[$this->dataSourceModel->getSourceId()]->setAttribute(PDO::ATTR_AUTOCOMMIT, $status ? 1 : 0);
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
		}
	}

	/**
	 * @param string $query
	 * @param array|null $params
	 * @return int|null
	 */
	public function getTotalCount(string $query = '', ?array $params = null): ?int
	{
		if ( empty($query) ) {
			$query = $this->latestQuery;
		}
		if ( empty($params) ) {
			$params = $this->latestParams;
		}

		$query = preg_replace(
			'/\s+OFFSET\s+\d+\s+ROWS(\s+FETCH\s+(FIRST|NEXT)\s+\d+\s+ROWS\s+ONLY)?\s*$/i',
			'',
			(string)$query
		);

		$query = SqlUtil::convertSelectToCountQuery($query);
		if ( empty($query) ) {
			return null;
		}

		if ( !$this->executeQuery($query, $params) ) {
			return null;
		}

		$countResult = $this->getResult();
		return is_null($countResult) ? null : intval($countResult);
	}
}
