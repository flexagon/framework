<?php
/**
 * Mysql
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

class Mysql extends DBSupporter implements RDB {
	/**
	 * @var PDO[]
	 */
    private array $pdo = [];
    private ?PDOStatement $stmt = null;

    /**
     * Prepared statements keyed by data source and SQL.
     *
     * @var PDOStatement[]
     */
    private array $statementCache = [];
    private string $connectionEncoding = 'utf8';
    private ?string $error = null;
    private ?string $latestQuery = null;
    private ?array $latestParams = null;
    private ?DataSourceModel $dataSourceModel = null;

    public function isConnected(): bool
    {
        if ( is_null($this->dataSourceModel) ) {
            return false;
        }
        return !empty($this->pdo[$this->dataSourceModel->getSourceId()]);
    }

    /**
     * @param DataSourceModel $dataSourceModel
     * @return bool
     * @see \_Flexagon\DB\Interfaces\RDB::connect()
     */
    public function connect(DataSourceModel $dataSourceModel): bool
    {
        try {
            $dsn = "mysql:host={$dataSourceModel->getHost()};port={$dataSourceModel->getPort()};dbname={$dataSourceModel->getDbName()}";

			if ( $dataSourceModel->getCharset() ) {
				$this->connectionEncoding = $dataSourceModel->getCharset();
			}
            $opt = [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . $this->connectionEncoding, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];

            $this->dataSourceModel = $dataSourceModel;

            if ( empty($this->pdo[$this->dataSourceModel->getSourceId()]) ) {
                $this->pdo[$this->dataSourceModel->getSourceId()] = new PDO($dsn, $dataSourceModel->getUsername(), $dataSourceModel->getPassword(), $opt);
            }

            if (empty($this->pdo[$this->dataSourceModel->getSourceId()])) {
                return false;
            }
        } catch (PDOException $e) {
            $this->flushLatestQuery();
            $this->error = $e->getMessage();

            error_log('[FLEXAGON] DB connection failed: ' . $e->getMessage());

            if ( \_Global::$DB_CONNECT_FAILURE_FATAL ) {
                exit();
            }
            return false;
        }

        return true;
    }

    /**
     * @return bool
     * @see \_Flexagon\DB\Interfaces\RDB::disconnect()
     */
    public function disconnect(): bool
    {
        if ($this->pdo[$this->dataSourceModel->getSourceId()]) {
            $this->pdo[$this->dataSourceModel->getSourceId()] = null;
        }

         
        $this->statementCache = [];

        return true;
    }

    public function query(string $query, bool $usePrepareStatement = true) : bool
    {
        if ( !$this->pdo[$this->dataSourceModel->getSourceId()]) {
            $this->error = "can't send query :: not connected DB";
            return false;
        }

        if ( empty($query) ) {
            return true;
        }

        $this->stmt = null;
        $this->latestQuery = $query;

        try {
			if ($usePrepareStatement) {
				$this->stmt = $this->prepareStatement($query);
			} else {
				$command = strtoupper(strtok(ltrim($query), " "));

				switch ($command) {
					case 'INSERT':
					case 'UPDATE':
					case 'DELETE':
					case 'REPLACE':
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
     * Prepare $query, reusing the statement when the same SQL comes round
     * again.
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

    public function bindParam($param, $value, int $dataType = PDO::PARAM_STR): void
    {
        $this->stmt->bindParam($param, $value, $dataType);
    }

    public function executePrepare($params = null ): bool
    {
        if ( !$this->pdo[$this->dataSourceModel->getSourceId()]) {
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
        if ( !$this->pdo[$this->dataSourceModel->getSourceId()]) {
            $this->flushLatestQuery();
            $this->error = "Can't send query :: not connected DB";
            return false;
        }

        if ( is_array($params) && empty($params)) {
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
			list($query, $params ) = SqlUtil::expandQueryParams($query, $params);

			if ( !$this->query($query, true) ) {
				$result = false;
			} else {
				$result = $this->executePrepare($params);
			}
		} else {
			$query = SqlUtil::combineQueryParams($query, $params);
			$result = $this->query($query , false);
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
        return SqlUtil::combineQueryParams($this->latestQuery, $this->latestParams);
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
        $this->stmt->debugDumpParams();
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
     * @param int $fetchMode [optional]<p>
     * Controls how the next row will be returned to the caller. This value
     * must be one of the PDO::FETCH_* constants,
     * defaulting to value of PDO::ATTR_DEFAULT_FETCH_MODE
     * (which defaults to PDO::FETCH_BOTH).
     * </p>
     * <p>
     * PDO::FETCH_ASSOC: returns an array indexed by column
     * name as returned in your result set
     * </p>
     * @return array|null
     * @see \_Flexagon\DB\Interfaces\RDB::getResultArray()
     */
    public function getResultAsArray(int $fetchMode = PDO::FETCH_BOTH): ?array
    {
        if (empty($this->stmt)) {
            return null;
        } else {
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
    }

    /**
     * @param int $fetchStyle [optional] PDO::FETCH_BOTH|PDO::FETCH_NUM|PDO::FETCH_ASSOC
     * Controls the contents of the returned array as documented in
     * <b>PDOStatement::fetch</b>.
     * Defaults to value of <b>PDO::ATTR_DEFAULT_FETCH_MODE</b>
     * (which defaults to <b>PDO::FETCH_BOTH</b>)
     * </p>
     * <p>
     * To return an array consisting of all values of a single column from
     * the result set, specify <b>PDO::FETCH_COLUMN</b>. You
     * can specify which column you want with the
     * <i>column-index</i> parameter.
     * </p>
     * <p>
     * To fetch only the unique values of a single column from the result set,
     * bitwise-OR <b>PDO::FETCH_COLUMN</b> with
     * <b>PDO::FETCH_UNIQUE</b>.
     * </p>
     * <p>
     * To return an associative array grouped by the values of a specified
     * column, bitwise-OR <b>PDO::FETCH_COLUMN</b> with
     * <b>PDO::FETCH_GROUP</b>.
     * </p>
     * @return array|null
     * @see \_Flexagon\DB\Interfaces\RDB::getAllResultAsArray()
     */
    public function getAllResultAsArray(int $fetchStyle = PDO::FETCH_BOTH): ?array
    {
        if (empty($this->stmt)) {
            return [];
        } else {
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
    }

    /**
     * @param int $fetchStyle PDO::FETCH_BOTH|PDO::FETCH_NUM|PDO::FETCH_ASSOC
     * Controls the contents of the returned array as documented in
     * <b>PDOStatement::fetch</b>.
     * Defaults to value of <b>PDO::ATTR_DEFAULT_FETCH_MODE</b>
     * (which defaults to <b>PDO::FETCH_BOTH</b>)
     * </p>
     * <p>
     * To return an array consisting of all values of a single column from
     * the result set, specify <b>PDO::FETCH_COLUMN</b>. You
     * can specify which column you want with the
     * <i>column-index</i> parameter.
     * </p>
     * <p>
     * To fetch only the unique values of a single column from the result set,
     * bitwise-OR <b>PDO::FETCH_COLUMN</b> with
     * <b>PDO::FETCH_UNIQUE</b>.
     * </p>
     * <p>
     * To return an associative array grouped by the values of a specified
     * column, bitwise-OR <b>PDO::FETCH_COLUMN</b> with
     * <b>PDO::FETCH_GROUP</b>.
     * </p>
     * @return mixed
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
     */
    public function getAllResultAsObject($object): array
    {
        return parent::getAllResultAsObjectFromArray($object, $this->getAllResultAsArray());
    }

    /**
     * @return string|null
     */
    public function getError(): ?string
    {
        return $this->error;
    }


    /**
     * @return null|string
     */
    public function getInsertId(): ?string
	{
        if (empty($this->pdo[$this->dataSourceModel->getSourceId()])) {
            return null;
        } else {
            try {
				$insertId = $this->pdo[$this->dataSourceModel->getSourceId()]->lastInsertId();
				if ( $insertId === false ) {
					return null;
				}
                return strval($insertId);
            } catch (PDOException $e) {
                $this->error = $e->getMessage();
                return null;
            }
        }
    }

	/**
	 * @return int|null
	 */
    public function getAffectedRows(): ?int
    {
        if (empty($this->stmt)) {
            return null;
        } else {
            try {
                return $this->stmt->rowCount();
            } catch (PDOException $e) {
                $this->error = $e->getMessage();
                return null;
            }
        }
    }


    /**
     * @param $object
     * @return object|null
     */
    public function getResultAsObject($object): ?object {
        return parent::getResultAsObject($object);
    }

	/**
	 * Prefer bound parameters. This exists for the rare literal that cannot be
	 * bound, and like any manual escaping it is unaware of the connection
	 * charset.
	 *
	 * @param string|null $string
	 * @return string|null
	 */
	public function escapeString(?string $string): ?string
	{
		if ($string === null) return null;

		return preg_replace_callback('/[\x00\x0A\x0D\x1A\x22\x27\x5C]/', function ($matches) {
			return '\\' . $matches[0];
		}, $string);
	}

    public function setAutoCommit(bool $status): void
    {
        $autoCommitSwitch = 0;
        if ( $status ) {
            $autoCommitSwitch = 1;
        }
        $this->pdo[$this->dataSourceModel->getSourceId()]->setAttribute(PDO::ATTR_AUTOCOMMIT,$autoCommitSwitch);
    }

	public function getTotalCount(string $query = '' , ?array $params = null ): ?int
	{
		if ( empty($query) ) {
			$query = $this->latestQuery;
		}
		if ( empty($params) ) {
			$params = $this->latestParams;
		}
		$query = SqlUtil::convertSelectToCountQuery($query);
		$this->executeQuery($query, $params);
		return $this->getResult();
	}
}
