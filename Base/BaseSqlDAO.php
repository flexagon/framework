<?php
/**
 * BaseSqlDAO
 *
 * Everything the relational DAOs do the same way. BaseMySqlDAO and
 * BaseMsSqlDAO carry only what their dialect actually forces: how an
 * identifier is quoted, how the table shape is read, and how a page is
 * expressed.
 *
 * Flexagon : PHP Development Framework
 *
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */
namespace _Flexagon\Base;

use _Flexagon\Base\Interfaces\BaseRDBDAO;
use _Flexagon\DB\Interfaces\RDB;
use _Flexagon\Libs\DataSourceManager;
use _Flexagon\Libs\PhpDocUtil;
use _Flexagon\Libs\StringUtil;
use _Flexagon\Models\DAOModel;
use _Flexagon\Models\RDBTableFieldModel;

abstract class BaseSqlDAO extends DAOModel implements BaseRDBDAO {
    /**
     * Typed against the interface rather than a driver class so that one
     * declaration serves both dialects. It always holds the instance
     * _createDriver() handed back.
     */
    protected ?RDB $db = null;
    protected string $tableName;

    /**
     * @var RDBTableFieldModel[]
     */
    protected array $tableFieldModelList = [];

    protected array $allFieldArray = [];
    protected array $primaryKeyFieldArray = [];
    protected array $notPrimaryKeyFieldArray = [];
    protected array $aiFieldArray = [];
    protected array $notAiFieldArray = [];
    protected array $usedFieldArray = [];
    protected ?string $latestSqlQuery = null;
    protected ?string $whereSqlQuery = null;
    protected ?array $whereSqlParams = null;
    protected ?string $orderSqlQuery = null;
    protected ?string $limitQuery = null;
    protected bool $ready = false;

    /**
     * Table metadata per data source and table, read once and shared.
     *
     * @var array<string, array>
     */

    private static array $schemaCache = [];

    /**
     * @return void
     */
    public static function clearSchemaCache(): void
    {
        self::$schemaCache = [];
    }

    /**
     * @return string
     */
    protected function _schemaCacheKey(): string
    {
        return $this->_DATA_SOURCE_MODEL->getSourceId() . '|' . $this->tableName;
    }

    /**
     * @return bool true when the instance was populated from the cache
     */
    protected function _loadSchemaFromCache(): bool
    {
        $cacheKey = $this->_schemaCacheKey();
        if ( !isset(self::$schemaCache[$cacheKey]) ) {
            return false;
        }

        $cached = self::$schemaCache[$cacheKey];
        $this->tableFieldModelList     = $cached['tableFieldModelList'];
        $this->allFieldArray           = $cached['allFieldArray'];
        $this->aiFieldArray            = $cached['aiFieldArray'];
        $this->notAiFieldArray         = $cached['notAiFieldArray'];
        $this->primaryKeyFieldArray    = $cached['primaryKeyFieldArray'];
        $this->notPrimaryKeyFieldArray = $cached['notPrimaryKeyFieldArray'];
        $this->ready = true;

        return true;
    }

    /**
     * @return void
     */
    protected function _storeSchemaToCache(): void
    {
        self::$schemaCache[$this->_schemaCacheKey()] = [
            'tableFieldModelList'     => $this->tableFieldModelList,
            'allFieldArray'           => $this->allFieldArray,
            'aiFieldArray'            => $this->aiFieldArray,
            'notAiFieldArray'         => $this->notAiFieldArray,
            'primaryKeyFieldArray'    => $this->primaryKeyFieldArray,
            'notPrimaryKeyFieldArray' => $this->notPrimaryKeyFieldArray,
        ];
    }

    public function __construct() {
        parent::__construct();
        $this->db = DataSourceManager::connect($this->_createDriver(), $this->_DATA_SOURCE_MODEL);
    }

    /**
     * A driver instance for this dialect. Called once per DAO; the manager
     * pools by data source id, so a second DAO on the same source reuses the
     * connection and drops this one.
     */
    abstract protected function _createDriver(): RDB;

    /**
     * Wrap an identifier the way this dialect spells it.
     */
    abstract protected static function _quote(string $identifier): string;

    /**
     * Page clause for the dialect, or '' when the arguments do not ask for one.
     */
    abstract protected function _getLimitQuery(int $pageNumber, int $countPerPage): string;

    /**
     * Read the table shape from the server into the field arrays and
     * tableFieldModelList, and set $this->ready.
     */
    abstract protected function _getReady(): bool;

    /**
     * Rows this dialect will accept in a single multi-row INSERT.
     */
    protected function _maxInsertAllChunkSize(): int
    {
        return 1000;
    }

    /**
     * ORDER BY to fall back on when a page is asked for without one.
     *
     * T-SQL only accepts OFFSET/FETCH after an ORDER BY; MySQL has no such
     * rule and answers '' here.
     */
    protected function _getPagingFallbackOrder(): string
    {
        return '';
    }

    public function __destruct() {
    }

    public function startTransaction(): void {
        $this->db->startTransaction();
    }

    public function rollback(): void {
        $this->db->rollback();
    }

    public function commit(): void {
        $this->db->commit();
    }


    protected function _flushLatestQuery(): void {
        $this->latestSqlQuery = '';
        $this->whereSqlQuery = '';
        $this->whereSqlParams = [];
        $this->orderSqlQuery = '';
        $this->limitQuery = '';

        $this->db->flushLatestQuery();
    }

    /**
     * @param object|array $modelObjectOrArray
     * @return mixed
     */
    protected function _insert(object|array $modelObjectOrArray): mixed
    {
        $lastInsertId = false;

        if ( is_object($modelObjectOrArray)) {
            $modelObjectOrArray = $this->_doAutoTimestamp($modelObjectOrArray, \FLEXAGON_CONST::QUERY_TYPE_INSERT);
            $modelObjectOrArray = $this->_doAutoTimestamp($modelObjectOrArray, \FLEXAGON_CONST::QUERY_TYPE_UPDATE);
        }
        list($query, $params) = $this->_getQueryAndParams(\FLEXAGON_CONST::QUERY_TYPE_INSERT, $modelObjectOrArray );

        if ( $this->db->executeQuery($query, $params) ) {
            $insertIdFieldName = null;
            if ( count($this->aiFieldArray)==1 ) {
                $insertIdFieldName  = $this->aiFieldArray[0];
            } elseif ( count($this->primaryKeyFieldArray) == 1 ) {
                $insertIdFieldName = $this->primaryKeyFieldArray[0];
            }

            $lastInsertId = $this->db->getInsertId();
            if ( !empty($lastInsertId) && !empty($insertIdFieldName)) {
                if ( array_key_exists($insertIdFieldName, $this->tableFieldModelList) ) {
                    if ( str_contains($this->tableFieldModelList[$insertIdFieldName]->getType(),'int')
                        || str_contains($this->tableFieldModelList[$insertIdFieldName]->getType(),'decimal')
                        || str_contains($this->tableFieldModelList[$insertIdFieldName]->getType(),'numeric') ) {
                        $lastInsertId = (int)$lastInsertId;
                    }
                }
            }
        }
        return $lastInsertId;
    }


    /**
     * Insert many rows with one statement per chunk.
     *
     * A per-row _insert() costs a round trip each; one multi-row INSERT covers
     * the whole chunk. @db_auto_timestamp and @encrypt are applied per row just
     * as they are for a single insert.
     *
     * Auto increment values are not returned — read them back if you need them.
     * Each chunk is a statement of its own, so wrap the call in a transaction
     * when partial success is unacceptable.
     *
     * @param array $modelObjectOrArrayList
     * @param int $chunkSize rows per statement
     * @return int rows inserted
     */
    protected function _insertAll(array $modelObjectOrArrayList, int $chunkSize = 500): int
    {
        if ( empty($modelObjectOrArrayList) ) {
            return 0;
        }

        $this->_flushLatestQuery();
        if ( !$this->_getReady() ) {
            return 0;
        }

        $fieldArray = $this->notAiFieldArray;
        if ( empty($fieldArray) ) {
            return 0;
        }

        foreach ( $modelObjectOrArrayList as $rowKey => $modelObjectOrArray ) {
            if ( is_object($modelObjectOrArray) ) {
                $modelObjectOrArray = $this->_doAutoTimestamp($modelObjectOrArray, \FLEXAGON_CONST::QUERY_TYPE_INSERT);
                $modelObjectOrArrayList[$rowKey] = $this->_doAutoTimestamp($modelObjectOrArray, \FLEXAGON_CONST::QUERY_TYPE_UPDATE);
            }
        }

        $omitFieldArray = [];
        foreach ( $this->tableFieldModelList as $fieldName => $fieldModel ) {
            if ( !$fieldModel->isGeneratedDefault() ) { continue; }

            $suppliedSomewhere = false;
            foreach ( $modelObjectOrArrayList as $modelObjectOrArray ) {
                if ( $this->_getFieldValue($modelObjectOrArray, $fieldName) !== null ) { $suppliedSomewhere = true; break; }
            }
            if ( !$suppliedSomewhere ) { $omitFieldArray[] = $fieldName; }
        }
        if ( !empty($omitFieldArray) ) {
            $fieldArray = array_values(array_diff($fieldArray, $omitFieldArray));
        }

        $chunkSize = max(1, min($chunkSize, $this->_maxInsertAllChunkSize()));
        $fieldLabelStr = implode(', ', array_map(function($s) { return static::_quote($s); }, $fieldArray));

        $insertedCount = 0;

        foreach ( array_chunk($modelObjectOrArrayList, $chunkSize) as $chunk ) {
            $rowPlaceholders = [];
            $params = [];

            foreach ( $chunk as $rowIndex => $modelObjectOrArray ) {
                $this->usedFieldArray = $fieldArray;
                $rowParams = $this->_getParams($modelObjectOrArray, null);

                $placeholders = [];
                foreach ( $fieldArray as $fieldName ) {
                    $paramName = 'R' . $rowIndex . '_' . strtoupper($fieldName);
                    $placeholders[] = ':' . $paramName;
                    $params[$paramName] = $rowParams[strtoupper($fieldName)] ?? null;
                }
                $rowPlaceholders[] = '(' . implode(', ', $placeholders) . ')';
            }

            $query = sprintf('INSERT INTO ' . static::_quote($this->tableName) . ' (%s) VALUES %s', $fieldLabelStr, implode(', ', $rowPlaceholders));

            if ( $this->db->executeQuery($query, $params) ) {
                $insertedCount += (int)$this->db->getAffectedRows();
            }
        }

        return $insertedCount;
    }

    /**
     * @param object|array $modelObjectOrArray
     * @return bool
     */
    protected function _update(object|array $modelObjectOrArray ): bool
    {
        if ( is_object($modelObjectOrArray)) {
            $modelObjectOrArray = $this->_doAutoTimestamp($modelObjectOrArray, \FLEXAGON_CONST::QUERY_TYPE_UPDATE);
        }

        list($query, $params) = $this->_getQueryAndParams(\FLEXAGON_CONST::QUERY_TYPE_UPDATE, $modelObjectOrArray, null, null );
        return $this->db->executeQuery($query, $params);
    }

    /**
     * @param object|array $modelObjectOrArray
     * @param string|null $whereSqlQuery
     * @param array|null $whereSqlParams
     * @return bool
     */
    protected function _updateByQuery(object|array $modelObjectOrArray, ?string $whereSqlQuery = null, ?array $whereSqlParams = null ): bool
    {
        if ( is_object($modelObjectOrArray)) {
            $modelObjectOrArray = $this->_doAutoTimestamp($modelObjectOrArray, \FLEXAGON_CONST::QUERY_TYPE_UPDATE);
        }

        list($query, $params) = $this->_getQueryAndParams(\FLEXAGON_CONST::QUERY_TYPE_UPDATE, $modelObjectOrArray, $whereSqlQuery, $whereSqlParams );
        return $this->db->executeQuery($query, $params);
    }

    /**
     * @param object|array $modelObjectOrArray
     * @param object|null $vo
     * @return mixed
     */
    protected function _select(object|array $modelObjectOrArray, ?object $vo = null ): mixed
    {
        list($query, $params) = $this->_getQueryAndParams(\FLEXAGON_CONST::QUERY_TYPE_SELECT, $modelObjectOrArray, null, null );

        if ($this->db->executeQuery($query, $params)) {
            if ($vo != null) {
                return $this->db->getResultAsObject($vo);
            } else {
                return $this->db->getResultAsArray();
            }
        }
        return null;
    }

    /**
     * @param string $whereSqlQuery
     * @param array|null $whereSqlParams
     * @param object|null $vo
     * @return mixed
     */
    protected function _selectByQuery(string $whereSqlQuery, ?array $whereSqlParams = null , ?object $vo = null): mixed {
        list($query, $params) = $this->_getQueryAndParams(\FLEXAGON_CONST::QUERY_TYPE_SELECT, null, $whereSqlQuery, $whereSqlParams );

        if ($this->db->executeQuery($query, $params)) {
            if ($vo != null) {
                return $this->db->getResultAsObject($vo);
            } else {
                return $this->db->getResultAsArray();
            }
        }
        return null;
    }

    /**
     * @param object|null $vo
     * @param int $pageNumber
     * @param int $countPerPage
     * @param string|null $whereSqlQuery
     * @param array|null $whereSqlParams
     * @param string|null $orderSqlQuery
     * @return array
     */
    protected function _selectList( ?object $vo = null, int $pageNumber = 1, int $countPerPage = 100, ?string $whereSqlQuery = null, ?array $whereSqlParams = null, ?string $orderSqlQuery = null): array
    {
        $orderSqlQuery = trim((string)$orderSqlQuery);
        $limitQuery = static::_getLimitQuery($pageNumber, $countPerPage);

        if ( empty($orderSqlQuery) ) {
            if ( !empty($limitQuery) ) {
                $orderSqlQuery = $this->_getPagingFallbackOrder();
            } else {
                $orderSqlQuery = '';
            }
        } else {
            $orderSqlQuery = sprintf('ORDER BY %s', $orderSqlQuery);
        }

        $extraSqlQuery = trim(sprintf('%s %s', $orderSqlQuery, $limitQuery));

        list($query, $params) = $this->_getQueryAndParams(\FLEXAGON_CONST::QUERY_TYPE_SELECT_LIST, null, $whereSqlQuery, $whereSqlParams , $extraSqlQuery);

        $this->whereSqlQuery = $whereSqlQuery;
        $this->whereSqlParams = $whereSqlParams;
        $this->orderSqlQuery = $orderSqlQuery;
        $this->limitQuery = $limitQuery;

        if ($this->db->executeQuery($query, $params)) {
            if (is_object($vo)) {
                return $this->db->getAllResultAsObject($vo);
            } else {
                return $this->db->getAllResultAsArray();
            }
        }
        return [];
    }

    /**
     * @param string|null $whereSqlQuery
     * @param array|null $whereSqlParams
     * @return int
     */
    protected function _selectTotalCount( ?string $whereSqlQuery = null, ?array $whereSqlParams = null): int
    {
        list($query, $params) = $this->_getQueryAndParams(\FLEXAGON_CONST::QUERY_TYPE_SELECT_COUNT, null, $whereSqlQuery, $whereSqlParams );

        $this->latestSqlQuery = $query;
        $result = 0;
        if ($this->db->executeQuery($query, $params)) {
            $result = intval($this->db->getResult());
        }
        return $result;
    }

    /**
     * Deletes by primary key.
     *
     * @param object|array $modelObjectOrArray
     * @return bool
     */
    protected function _delete(object|array $modelObjectOrArray): bool
    {
        if ( !is_object($modelObjectOrArray) && !is_array($modelObjectOrArray) ) {
            throw new \InvalidArgumentException();
        }

        list($query, $params) = $this->_getQueryAndParams(\FLEXAGON_CONST::QUERY_TYPE_DELETE, $modelObjectOrArray, null, null );
        return $this->db->executeQuery($query, $params);
    }

    /**
     * @param string $whereQuery
     * @param array|null $whereSqlParams
     * @return bool
     */
    protected function _deleteByQuery(string $whereQuery, ?array $whereSqlParams = null ): bool
    {
        list($query, $params) = $this->_getQueryAndParams(\FLEXAGON_CONST::QUERY_TYPE_DELETE, null, $whereQuery, $whereSqlParams );
        return $this->db->executeQuery($query, $params);
    }

    /**
     * @return bool
     */
    protected function _truncate(): bool
    {
        list($query, $params) = $this->_getQueryAndParams( \FLEXAGON_CONST::QUERY_TYPE_TRUNCATE);
        return $this->db->executeQuery($query);
    }

    /**
     * @return string|null
     */
    public function getError(): ?string
    {
        return $this->db->getError();
    }

    /**
     * @return string
     */
    public function debugQuery(): string
    {
        return (string)$this->db->debugQuery();
    }

    public function debugDumpParams(): void
    {
        $this->db->debugDumpParams();
    }


    /**
     * @param int $queryType
     * @param object|array|null $modelObjectOrArray
     * @param string|null $whereSqlQuery
     * @param array|null $whereParams
     * @param string|null $extraSqlQuery
     * @return array
     */
    protected function _getQueryAndParams(int $queryType, $modelObjectOrArray = null, ?string $whereSqlQuery = null, ?array $whereParams = null , ?string $extraSqlQuery = null ) : array {
        $this->_flushLatestQuery();
        $this->_getReady();

        $sqlQuery = '';
        $sqlParams = [];

        if (!empty($this->tableFieldModelList)) {
            $this->usedFieldArray = [];

            $quotedTable = static::_quote($this->tableName);
            $allFieldLabelStr = implode(', ', array_map(function($s) { return static::_quote($s); }, $this->allFieldArray));

            $tmpFieldArray = $this->notAiFieldArray;
            $tmpPrimaryKeyFieldArray = $this->primaryKeyFieldArray;
            $tmpNotPrimaryKeyFieldArray = $this->notPrimaryKeyFieldArray;

             
            $omitFieldArray = $this->_getDatabaseDefaultFieldArray($modelObjectOrArray);
            if ( !empty($omitFieldArray) ) {
                $tmpFieldArray = array_values(array_diff($tmpFieldArray, $omitFieldArray));
                $tmpNotPrimaryKeyFieldArray = array_values(array_diff($tmpNotPrimaryKeyFieldArray, $omitFieldArray));
            }

            switch ($queryType) {
                case \FLEXAGON_CONST::QUERY_TYPE_INSERT:
                    $this->usedFieldArray = array_merge($this->usedFieldArray, $tmpFieldArray);

                    $fieldLabelStr = implode(', ', array_map(function($s) { return static::_quote($s); }, $tmpFieldArray));

                    array_walk($tmpFieldArray, function (&$name) { $name = ':'.strtoupper($name); });
                    $valueLabelStr = implode(', ', $tmpFieldArray);

                    $sqlQuery = sprintf('INSERT INTO %s (%s) VALUES (%s)', $quotedTable, $fieldLabelStr, $valueLabelStr);
                    break;

                case \FLEXAGON_CONST::QUERY_TYPE_UPDATE:
                    $this->usedFieldArray = array_merge($this->usedFieldArray, $tmpPrimaryKeyFieldArray, $tmpNotPrimaryKeyFieldArray);

                    array_walk($tmpNotPrimaryKeyFieldArray, function (&$name) { $name = static::_quote($name).' = :'.strtoupper($name); });

                    if ( !empty($modelObjectOrArray) && empty($whereSqlQuery) ) {
                        array_walk($tmpPrimaryKeyFieldArray, function (&$name) { $name = static::_quote($name).' = :'.strtoupper($name); });
                        $whereSqlQuery = '';
                    } else {
                        $tmpPrimaryKeyFieldArray = [];
                    }
                    $sqlQuery = trim(sprintf('UPDATE %s SET %s WHERE %s %s', $quotedTable, implode(', ', $tmpNotPrimaryKeyFieldArray), implode(' AND ',$tmpPrimaryKeyFieldArray), $whereSqlQuery));
                    break;

                case \FLEXAGON_CONST::QUERY_TYPE_SELECT:
                    $this->usedFieldArray = array_merge($this->usedFieldArray, $tmpPrimaryKeyFieldArray);

                    $paramFieldArray = $this->_getProbeFieldArray($modelObjectOrArray);

                    if ( !empty($paramFieldArray) ) {
                        $this->usedFieldArray = $paramFieldArray;

                        array_walk($paramFieldArray, function (&$name) { $name = static::_quote($name).' = :'.strtoupper($name); });

                        if ( !empty($paramFieldArray) && !empty($whereSqlQuery) ) {
                            $whereSqlQuery = sprintf(' AND %s',$whereSqlQuery);
                        }
                    }
                    $sqlQuery = trim(sprintf('SELECT %s FROM %s WHERE %s %s', $allFieldLabelStr, $quotedTable, implode(' AND ',$paramFieldArray), $whereSqlQuery));
                    break;

                case \FLEXAGON_CONST::QUERY_TYPE_SELECT_LIST:
                    if ( !empty($whereSqlQuery) ) {
                        $whereSqlQuery = sprintf('WHERE %s',$whereSqlQuery);
                    }
                    $sqlQuery = trim(sprintf('SELECT %s FROM %s %s %s', $allFieldLabelStr, $quotedTable, $whereSqlQuery, $extraSqlQuery));
                    break;

                case \FLEXAGON_CONST::QUERY_TYPE_SELECT_COUNT:
                    if ( !empty($whereSqlQuery) ) {
                        $whereSqlQuery = sprintf('WHERE %s',$whereSqlQuery);
                    }
                    $sqlQuery = trim(sprintf('SELECT COUNT(*) FROM %s %s', $quotedTable, $whereSqlQuery));
                    break;

                case \FLEXAGON_CONST::QUERY_TYPE_DELETE:
                    $this->usedFieldArray = array_merge($this->usedFieldArray, $tmpPrimaryKeyFieldArray);

                    $paramFieldArray = $tmpPrimaryKeyFieldArray;
                    if ( empty($modelObjectOrArray) ) {
                        $paramFieldArray = [];
                        $this->usedFieldArray = [];
                    }

                    array_walk($paramFieldArray, function (&$name) { $name = static::_quote($name).' = :'.strtoupper($name); });

                    if ( !empty($paramFieldArray) && !empty($whereSqlQuery) ) {
                        $whereSqlQuery = sprintf(' AND %s',$whereSqlQuery);
                    }
                    $sqlQuery = trim(sprintf('DELETE FROM %s WHERE %s %s', $quotedTable, implode(' AND ',$paramFieldArray), $whereSqlQuery));
                    break;

                case \FLEXAGON_CONST::QUERY_TYPE_TRUNCATE:
                    $sqlQuery = sprintf('TRUNCATE TABLE %s', $quotedTable);
                    break;
            }
            $sqlParams = $this->_getParams($modelObjectOrArray, $whereParams);
        }
        return [$sqlQuery, $sqlParams];
    }

    /**
     * Columns the model or array carries a select-by-example criterion for.
     *
     * An array is taken as given: its keys are already column names.
     *
     * @param object|array|null $modelObjectOrArray
     * @return array column names
     */

    protected function _getProbeFieldArray(object|array|null $modelObjectOrArray): array
    {
        if ( empty($modelObjectOrArray) ) {
            return [];
        }
        if ( is_array($modelObjectOrArray) ) {
            return array_keys($modelObjectOrArray);
        }

        $probeFieldArray = [];
        foreach ( $this->allFieldArray as $fieldName ) {
            $value = $this->_getFieldValueFromModel($modelObjectOrArray, ucfirst(StringUtil::convertToCamelcase($fieldName)));
             
            if ( $value === null || $value === '' ) { continue; }
            $probeFieldArray[] = $fieldName;
        }
        return $probeFieldArray;
    }

    /**
     * @return array
     */
    protected function _getUsedFieldArray(): array
    {
        return $this->usedFieldArray;
    }

    /**
     * The value the caller supplies for one column, whichever shape it came in.
     *
     * @param object|array|null $modelObjectOrArray
     * @param string $fieldName column name
     * @return mixed null when nothing was supplied for that column
     */
    protected function _getFieldValue(object|array|null $modelObjectOrArray, string $fieldName): mixed
    {
        if ( empty($modelObjectOrArray) ) { return null; }

        $words = StringUtil::convertToCamelcase($fieldName);

        if ( is_object($modelObjectOrArray) ) {
            return $this->_getFieldValueFromModel($modelObjectOrArray, ucfirst($words));
        }

        if ( array_key_exists($words, $modelObjectOrArray) ) { return $modelObjectOrArray[$words]; }
        if ( array_key_exists($fieldName, $modelObjectOrArray) ) { return $modelObjectOrArray[$fieldName]; }

        return null;
    }

    /**
     * Columns to leave out of the statement so the database fills them itself.
     *
     * A default the database evaluates -- CURRENT_TIMESTAMP, GETDATE() -- is an
     * expression, not a value, so there is nothing to bind for a column the
     * caller said nothing about. Naming it anyway failed the whole statement:
     *
     *   before  Incorrect datetime value: 'CURRENT_TIMESTAMP' for column 't_now'
     *
     * Only columns that would otherwise have failed are dropped, so a table
     * without an expression default sees no change at all.
     *
     * @param object|array|null $modelObjectOrArray
     * @return array column names
     */
    protected function _getDatabaseDefaultFieldArray(object|array|null $modelObjectOrArray): array
    {
        $omitFieldArray = [];

        foreach ( $this->tableFieldModelList as $fieldName => $fieldModel ) {
            if ( !$fieldModel->isGeneratedDefault() ) { continue; }
            if ( $this->_getFieldValue($modelObjectOrArray, $fieldName) !== null ) { continue; }
            $omitFieldArray[] = $fieldName;
        }

        return $omitFieldArray;
    }

    /**
     * Read one column straight off the model.
     *
     * Reading by column name asks for exactly what the schema declares.
     * get*() is preferred; is*() is used when the model has only that.
     *
     * @param object $modelObject
     * @param string $wordsFirstUpper column name in camel case, first letter upper
     * @return mixed null when the model has no accessor for the column
     */
    protected function _getFieldValueFromModel(object $modelObject, string $wordsFirstUpper): mixed
    {
        $getterName = null;
        foreach ( ["get{$wordsFirstUpper}", "is{$wordsFirstUpper}"] as $candidateName ) {
            if ( method_exists($modelObject, $candidateName) ) { $getterName = $candidateName; break; }
        }
        if ( $getterName === null ) { return null; }

        $value = $modelObject->$getterName();

         
        if ( $value instanceof \BackedEnum ) { return $value->value; }
        if ( is_object($value) && method_exists($value, 'getArray') ) { return $value->getArray(false); }

        return $value;
    }

    /**
     * @param object|array|null $modelObjectOrArray
     * @param array|null $whereSqlParams
     * @return array
     */
    protected function _getParams(object|array|null $modelObjectOrArray = null , ?array $whereSqlParams = null ): array
    {
        if (empty($this->tableFieldModelList) ) {
            return [];
        }

        $fieldLabels = [];
        $params = [];

        if ( !empty($this->_getUsedFieldArray())) {
            $fieldLabels = $this->_getUsedFieldArray();
        }

        if ( is_object($modelObjectOrArray) && method_exists($modelObjectOrArray, 'encrypt') ) {
            $modelObjectOrArray->encrypt();
        }

        foreach ( $fieldLabels as $fieldName ) {
            if (!empty($modelObjectOrArray)) {
                $result = $this->_getFieldValue($modelObjectOrArray, $fieldName);

                if ($result === null && array_key_exists($fieldName, $this->tableFieldModelList)) {
                    $result = $this->tableFieldModelList[$fieldName]->getDefault();
                }

                $params[strtoupper($fieldName)] = $result;
            }
        }

        if ( is_array($whereSqlParams) && count($whereSqlParams) > 0 ) {
            $params = array_merge($params, $whereSqlParams);
        }
        return $params;
    }

    /**
     * @param object $modelObject
     * @param int $queryType
     * @return object
     */
    protected function _doAutoTimestamp(object $modelObject, int $queryType): object {
        switch ($queryType) {
            case \FLEXAGON_CONST::QUERY_TYPE_INSERT:
                if ( !\_Global::$USE_AUTO_CREATED_AT_TIMESTAMP ) { return $modelObject; }
                $queryTypeString = 'insert';
                break;
            case \FLEXAGON_CONST::QUERY_TYPE_UPDATE:
                if ( !\_Global::$USE_AUTO_UPDATED_AT_TIMESTAMP ) { return $modelObject; }
                $queryTypeString = 'update';
                break;
            default:
                $queryTypeString = '';
        }

        if ( !empty($queryTypeString) ) {
            $properties = PhpDocUtil::findProperties($modelObject, \FLEXAGON_CONST::ANNOTATION_TAG_CLASS_PROPERTY_DB_AUTO_TIMESTAMP, $queryTypeString);

            $now = time();
            foreach ( $properties as $propertyName ) {
                $setterName = 'set' . ucfirst($propertyName);
                if ( method_exists($modelObject, $setterName) ) { $modelObject->$setterName($now); }
            }
        }
        return $modelObject;
    }

    /**
     * @param array $conditionArray
     * @return string
     */
    protected function _getWhereQuery(array $conditionArray): string
    {
        if ( !empty($conditionArray) ) {
            $conditionArray = array_filter($conditionArray, function($v) {
                return trim($v) !== '';
            });
            return sprintf(' WHERE %s', implode(' AND ', $conditionArray));
        }
        return '';
    }


    /**
     * @param string $query
     * @param array|null $params
     * @return int
     */
    public function getTotalCount(string $query = '' , ?array $params = null): int
    {
        return intval($this->db->getTotalCount($query, $params));
    }
}
