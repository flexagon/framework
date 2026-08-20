<?php
/**
 * RDB Interface
 *
 * Flexagon : PHP Development Framework
 *
 * @author        Younghwan Yong <young@phpk.org>
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */
namespace _Flexagon\DB\Interfaces;

use _Flexagon\Models\DataSourceModel;
use PDO;

interface RDB {
    /**
     * @param DataSourceModel $dataSourceModel
     * @return mixed
     */
	public function connect(DataSourceModel $dataSourceModel);
	public function disconnect();

    /**
     * @param string $query
     * @return bool
     */
	public function query(string $query): bool;

	/**
	 * @param string|int $param
	 * @param mixed $value
	 * @param int $dataType
	 * @return void
	 */
	public function bindParam($param, $value, int $dataType = PDO::PARAM_STR ): void;

	public function executePrepare(?array $params = null );

    /**
     * @param string $query
     * @param array|null $params
     * @return mixed
     */
	public function executeQuery(string $query, ?array $params = null): bool;
	/**
	 * Both of these, and getAffectedRows(), answer the same number: what
	 * the statement touched. The names have been in the interface long
	 * enough to be worth keeping, but reach for getAffectedRows() in new
	 * code -- it is the one that says what it means.
	 */
	public function getTotalNumRows();
	public function getResultNumRows();
	public function getResult();
	public function getResultAsArray();
    public function getResultAsObject($object);
    public function getAllResultAsArray();
    public function getAllResultAsObject($object);
	public function startTransaction();
	public function escapeString(?string $string);
	public function rollback();
	public function commit();

	public function getAffectedRows(): ?int;
	public function getInsertId(): ?string;
	public function getError(): ?string;
	public function getTotalCount(string $query = '', ?array $params = null): ?int;
	public function flushLatestQuery(): void;
	public function debugQuery(): ?string;
	public function debugDumpParams(): void;
}
