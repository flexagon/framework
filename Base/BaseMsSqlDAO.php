<?php
/**
 * BaseMsSqlDAO
 *
 * Flexagon : PHP Development Framework
 *
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */
namespace _Flexagon\Base;

use _Flexagon\DB\Interfaces\RDB;
use _Flexagon\DB\Mssql;
use _Flexagon\Models\RDBTableFieldModel;
use Exception;

/**
 * SQL Server dialect.
 *
 * Everything a DAO does lives in BaseSqlDAO; what is left here is what T-SQL
 * forces: bracket identifiers, INFORMATION_SCHEMA instead of DESCRIBE,
 * OFFSET/FETCH instead of LIMIT, and defaults reported as expressions.
 *
 * $this->db holds a Mssql instance.
 */
abstract class BaseMsSqlDAO extends BaseSqlDAO {
    protected function _createDriver(): RDB
    {
        return new Mssql();
    }

    /**
     * T-SQL caps a multi-row INSERT at 1000 rows.
     */
    protected function _maxInsertAllChunkSize(): int
    {
        return 1000;
    }

    /**
     * OFFSET/FETCH is only legal after an ORDER BY.
     */
    protected function _getPagingFallbackOrder(): string
    {
        return 'ORDER BY (SELECT NULL)';
    }

    /**
     * Wrap an identifier in brackets, keeping any schema prefix intact.
     * "dbo.users" becomes "[dbo].[users]".
     *
     * @param string $identifier
     * @return string
     */
    protected static function _quote(string $identifier): string {
        $parts = explode('.', $identifier);
        foreach ($parts as $index => $part) {
            $parts[$index] = '[' . str_replace(']', ']]', trim($part)) . ']';
        }
        return implode('.', $parts);
    }
    /**
     * T-SQL paging clause. Only valid directly after an ORDER BY, which
     * _selectList() guarantees.
     *
     * @param int $pageNumber
     * @param int $countPerPage
     * @return string
     */
    protected function _getLimitQuery(int $pageNumber, int $countPerPage): string {
        if ( $countPerPage > 0 && $pageNumber > 0 ) {
            return sprintf('OFFSET %d ROWS FETCH NEXT %d ROWS ONLY', ($pageNumber - 1) * $countPerPage, $countPerPage);
        }
        return '';
    }
    /**
     * Read the table shape once per request.
     *
     * SQL Server has no DESCRIBE, so column, primary key and IDENTITY data are
     * pulled from INFORMATION_SCHEMA and normalised onto the same vocabulary
     * RDBTableFieldModel already uses for MySQL ('pri', 'auto_increment').
     *
     * @return bool
     */
    protected function _getReady(): bool
    {
        if (empty($this->tableName)) {
            return false;
        }

        if ($this->ready) {
            return true;
        }

        if ($this->_loadSchemaFromCache()) {
            return true;
        }

        $tableParts = explode('.', $this->tableName);
        $bareTableName = trim(end($tableParts), '[]');
        $schemaName = count($tableParts) > 1 ? trim($tableParts[count($tableParts) - 2], '[]') : null;

        $query = 'SELECT c.COLUMN_NAME AS field_name,'
            . ' c.DATA_TYPE AS field_type,'
            . ' c.IS_NULLABLE AS is_nullable,'
            . ' c.COLUMN_DEFAULT AS column_default,'
            . ' COLUMNPROPERTY(OBJECT_ID(QUOTENAME(c.TABLE_SCHEMA) + \'.\' + QUOTENAME(c.TABLE_NAME)), c.COLUMN_NAME, \'IsIdentity\') AS is_identity,'
            . ' CASE WHEN pk.COLUMN_NAME IS NULL THEN 0 ELSE 1 END AS is_primary_key'
            . ' FROM INFORMATION_SCHEMA.COLUMNS c'
            . ' LEFT JOIN ('
            . '   SELECT ku.TABLE_SCHEMA, ku.TABLE_NAME, ku.COLUMN_NAME'
            . '   FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc'
            . '   INNER JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE ku'
            . '     ON tc.CONSTRAINT_NAME = ku.CONSTRAINT_NAME'
            . '    AND tc.CONSTRAINT_SCHEMA = ku.CONSTRAINT_SCHEMA'
            . '   WHERE tc.CONSTRAINT_TYPE = \'PRIMARY KEY\''
            . ' ) pk ON pk.TABLE_SCHEMA = c.TABLE_SCHEMA AND pk.TABLE_NAME = c.TABLE_NAME AND pk.COLUMN_NAME = c.COLUMN_NAME'
            . ' WHERE c.TABLE_NAME = :TABLE_NAME';

        $params = ['TABLE_NAME' => $bareTableName];
        if ( !is_null($schemaName) ) {
            $query .= ' AND c.TABLE_SCHEMA = :TABLE_SCHEMA';
            $params['TABLE_SCHEMA'] = $schemaName;
        }
        $query .= ' ORDER BY c.ORDINAL_POSITION';

        if ( !$this->db->executeQuery($query, $params) ) {
            $this->ready = false;
            return false;
        }

        $result = $this->db->getAllResultAsArray(\PDO::FETCH_ASSOC);
        if ( empty($result) ) {
            $this->ready = false;
            return false;
        }

        try {
            foreach ($result as $row) {
                $fieldModel = new RDBTableFieldModel();
                $fieldModel->setName($row['field_name']);
                $fieldModel->setType($row['field_type']);
                $fieldModel->setNull(strtoupper((string)$row['is_nullable']) === 'YES' ? 'YES' : 'NO');
                $fieldModel->setKey(intval($row['is_primary_key']) === 1 ? 'PRI' : '');
                $fieldModel->setExtra(intval($row['is_identity']) === 1 ? 'auto_increment' : '');

                $columnDefault = self::_normalizeColumnDefault($row['column_default']);
                $fieldModel->setDefault($columnDefault);
                $fieldModel->setGeneratedDefault($row['column_default'] !== null && $columnDefault === null);

                $this->tableFieldModelList[$fieldModel->getName()] = $fieldModel;
                $this->allFieldArray[] = $fieldModel->getName();

                if ($fieldModel->isAutoIncrement()) {
                    $this->aiFieldArray[] = $fieldModel->getName();
                } else {
                    $this->notAiFieldArray[] = $fieldModel->getName();
                }

                if ($fieldModel->isPrimaryKey()) {
                    $this->primaryKeyFieldArray[] = $fieldModel->getName();
                } else {
                    $this->notPrimaryKeyFieldArray[] = $fieldModel->getName();
                }
            }

            $this->ready = true;
            $this->_storeSchemaToCache();
            return true;
        } catch (Exception $e) {
            $this->ready = false;
            return false;
        }
    }
    /**
     * Turn a SQL Server column default into the value it stands for.
     *
     * INFORMATION_SCHEMA reports the default as the *expression* that produced
     * it, wrapped in its own parentheses, so a table says ((0)) where it means
     * 0 and ('Seoul') where it means Seoul. Handing that text to the driver
     * failed outright -- "Conversion failed when converting the varchar value
     * '((0))' to data type bit" -- and MySQL, which reports plain values, needs
     * none of this.
     *
     *   ((0))       0            ('it''s')   it's
     *   ((-1))      -1           ('')        (empty string)
     *   ('Seoul')   Seoul        (getdate()) null
     *   (N'서울')    서울
     *
     * A default that is a function call or any other expression has no value to
     * bind, so it comes back null.
     *
     * @param string|null $expression
     * @return string|null
     */
    protected static function _normalizeColumnDefault(?string $expression): ?string
    {
        if ( $expression === null ) { return null; }

        $expression = self::_stripOuterParentheses(trim($expression));
        if ( $expression === '' ) { return null; }

         
        if ( strlen($expression) > 1 && strcasecmp($expression[0], 'N') === 0 && $expression[1] === "'" ) {
            $expression = substr($expression, 1);
        }

        if ( strlen($expression) >= 2 && $expression[0] === "'" && str_ends_with($expression, "'") ) {
            return str_replace("''", "'", substr($expression, 1, -1));
        }

        if ( is_numeric($expression) ) { return $expression; }

        if ( strcasecmp($expression, 'NULL') === 0 ) { return null; }

        return null;
    }
    /**
     * Drop parenthesis pairs that wrap the whole expression, leaving any that
     * belong to it -- (getdate()) gives up its outer pair and keeps its own.
     * Quoted sections are skipped so a default of ')' survives.
     *
     * @param string $expression
     * @return string
     */
    private static function _stripOuterParentheses(string $expression): string
    {
        while ( strlen($expression) >= 2 && $expression[0] === '(' && str_ends_with($expression, ')') ) {
            $depth = 0;
            $inQuote = false;
            $wrapsWhole = true;
            $length = strlen($expression);

            for ( $i = 0; $i < $length; $i++ ) {
                $character = $expression[$i];

                 
                if ( $character === "'" ) { $inQuote = !$inQuote; continue; }
                if ( $inQuote ) { continue; }

                if ( $character === '(' ) {
                    $depth++;
                } elseif ( $character === ')' ) {
                    $depth--;
                    if ( $depth === 0 && $i < $length - 1 ) { $wrapsWhole = false; break; }
                }
            }

            if ( !$wrapsWhole ) { break; }
            $expression = trim(substr($expression, 1, -1));
        }

        return $expression;
    }
}
