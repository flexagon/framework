<?php
/**
 * DataSourceManager
 *
 * Connection pooling and the transaction spanning them.
 *
 * Flexagon : PHP Development Framework
 *
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */
namespace _Flexagon\Libs;

use _Flexagon\Models\DataSourceModel;

class DataSourceManager
{
    private static bool $transaction = false;
    private static array $dbSources = [];

    public static function startTransaction(): void {
        self::$transaction = true;
    }

    public static function commit(): void {
        try {
            foreach ( array_reverse(self::$dbSources) as $dbs ) {
                $dbs->commit();
            }
        } finally {
            self::$transaction = false;
        }
    }

    public static function rollback(): void {
        try {
            foreach ( array_reverse(self::$dbSources) as $dbs ) {
                $dbs->rollback();
            }
        } finally {
            self::$transaction = false;
        }
    }

    public static function isTransaction(): bool
    {
        return self::$transaction;
    }

    public static function existsDataSourceId(string $sourceId): bool
    {
        return array_key_exists($sourceId, self::$dbSources);
    }

    /**
     * @deprecated Use existsDataSourceId(). Kept so existing callers keep working.
     */
    public static function existDataSourceId(string $sourceId): bool
    {
        return self::existsDataSourceId($sourceId);
    }

    public static function connect(object $db, DataSourceModel $dataSourceModel): object
    {
        if ( !empty(self::$dbSources) && array_key_exists($dataSourceModel->getSourceId(), self::$dbSources) ) {
            if ( !self::$dbSources[$dataSourceModel->getSourceId()]->isConnected() ) {
                self::$dbSources[$dataSourceModel->getSourceId()]->connect($dataSourceModel);
            }
        } else {
            self::$dbSources[$dataSourceModel->getSourceId()] = $db;
            self::$dbSources[$dataSourceModel->getSourceId()]->connect($dataSourceModel);
        }
        return self::$dbSources[$dataSourceModel->getSourceId()];
    }

    public static function disconnectAll(): void {
        foreach ( self::$dbSources as $dbs ) {
            $dbs->disconnect();
        }
        self::$dbSources = [];
        self::$transaction = false;
    }
}