<?php
/**
 * DataSourceModel
 *
 * Flexagon : PHP Development Framework
 *
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */
namespace _Flexagon\Models;

class DataSourceModel {
	private string $sourceId = '';
	private string $username = '';
	private string $password = '';
	private string $host = '';
	private string $dbName = '';
	private int $port = 0;
	private string $charset = 'utf8mb4';
    private bool $transaction = false;
	private bool $usePrepareStatement = true;

	function __construct(string $host, int $port, string $username, string $password, string $dbName, ?string $charset = null, ?bool $usePrepareStatement = null) {
		$this->setHost($host);
		$this->setPort($port);
		$this->setUsername($username);
		$this->setPassword($password);
		$this->setDbName($dbName);
		if ( !empty($charset) ) {
			$this->setCharset($charset);
		}
		if ( !is_null($usePrepareStatement) ) {
			$this->setUsePrepareStatement($usePrepareStatement);
		}

        $this->registrySourceId();
	}

    /**
     * @return void
     */
    public function registrySourceId(): void
    {
        $this->setSourceId($this->generateSourceId());
    }

    /**
     * @return string
     */
    private function generateSourceId(): string
    {
        return sha1("{$this->getUsername()}@{$this->getHost()}/{$this->getDbName()}");
    }

    /**
     * @return string|null
     */
    public function getSourceId(): ?string
    {
        return $this->sourceId;
    }
    /**
     * @param string|null $sourceId
     */
    public function setSourceId(?string $sourceId): void
    {
        $this->sourceId = $sourceId;
    }
	public function getUsername(): ?string
    {
		return $this->username;
	}
	public function setUsername($username): void
    {
		$this->username = $username;
	}
	public function getPassword(): ?string
    {
		return $this->password;
	}
	public function setPassword($password): void
    {
		$this->password = $password;
	}
	public function getHost(): ?string
    {
		return $this->host;
	}
	public function setHost($host): void
    {
		$this->host = $host;
	}
	public function getDbName(): ?string
    {
		return $this->dbName;
	}
	public function setDbName($dbName): void
    {
		$this->dbName = $dbName;
	}
	public function getPort(): int
    {
		return $this->port;
	}
	public function setPort($port): void
    {
		$this->port = $port;
	}

	public function getCharset(): string
	{
		return $this->charset;
	}

	public function setCharset(string $charset): void
	{
		$this->charset = $charset;
	}

    /**
     * @return bool
     */
    public function isTransaction(): bool
    {
        return $this->transaction;
    }

    /**
     * @param bool $transaction
     */
    public function setTransaction(bool $transaction): void
    {
        $this->transaction = $transaction;
    }

	public function isUsePrepareStatement(): bool
	{
		return $this->usePrepareStatement;
	}

	public function setUsePrepareStatement(bool $usePrepareStatement): void
	{
		$this->usePrepareStatement = $usePrepareStatement;
	}
}