<?php
/**
 * BaseMySqlDAO
 *
 * Flexagon : PHP Development Framework
 *
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */
namespace _Flexagon\Base;

use _Flexagon\DB\Interfaces\RDB;
use _Flexagon\DB\Mysql;
use _Flexagon\Models\RDBTableFieldModel;
use Exception;

/**
 * MySQL and MariaDB dialect.
 *
 * Everything a DAO does lives in BaseSqlDAO; what is left here is backtick
 * identifiers, DESCRIBE for the table shape, and LIMIT for paging.
 *
 * $this->db holds a Mysql instance.
 */
abstract class BaseMySqlDAO extends BaseSqlDAO {
    protected function _createDriver(): RDB
    {
        return new Mysql();
    }

    protected function _maxInsertAllChunkSize(): int
    {
        return 5000;
    }

    /**
     * Wrap an identifier in backticks, keeping any schema prefix intact.
     * "app.users" becomes "`app`.`users`".
     *
     * @param string $identifier
     * @return string
     */
    protected static function _quote(string $identifier): string
    {
        $parts = explode('.', $identifier);
        foreach ($parts as $index => $part) {
            $parts[$index] = '`' . str_replace('`', '``', trim($part)) . '`';
        }
        return implode('.', $parts);
    }

    /**
     * @param int $pageNumber
     * @param int $countPerPage
     * @return string
     */
    protected function _getLimitQuery(int $pageNumber, int $countPerPage): string
    {
        if ( $countPerPage > 0 && $pageNumber > 0 ) {
            return sprintf('LIMIT %d,%d', ($pageNumber - 1) * $countPerPage, $countPerPage);
        }
        return '';
    }

    /**
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

        {
            $query = sprintf('DESCRIBE %s', static::_quote($this->tableName));
            $this->db->executeQuery($query);
            $result = $this->db->getAllResultAsArray(\PDO::FETCH_OBJ);

            try {
                for ($i = 0; $i < count($result); $i++) {
                    $row = $result[$i];

                    $fieldModel = new RDBTableFieldModel();
                    $fieldModel->setName($row->Field);
                    $fieldModel->setNull($row->Null);
                    $fieldModel->setKey($row->Key);
                    $fieldModel->setExtra($row->Extra);

                    $fieldModel->setGeneratedDefault(str_contains(strtolower((string)$row->Extra), 'default_generated'));
                    $fieldModel->setDefault($fieldModel->isGeneratedDefault() ? null : $row->Default);

                    preg_match('/^([a-z^\w]+)/i', $row->Type, $match);
                    $fieldModel->setType(strtolower($match[1]));

                     
                    $this->tableFieldModelList[$row->Field] = $fieldModel;
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
    }
}
