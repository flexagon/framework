<?php
/**
 * RDBTableFieldModel
 *
 * One column as the database describes it.
 *
 * Flexagon : PHP Development Framework
 *
 * @author        graphittie
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */

namespace _Flexagon\Models;

class RDBTableFieldModel {
    private string $name = '';
    private string $type = '';
    private ?string $null = null;
    private ?string $key = null;
    private ?string $default = null;
    private ?string $extra = null;
    private bool $generatedDefault = false;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = trim($name);
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = strtolower(trim($type));
    }

    public function getNull(): ?string
    {
        return $this->null;
    }

    public function setNull(?string $null): void
    {
        $this->null = $null === null ? null : strtolower(trim($null));
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function setKey(?string $key): void
    {
        $this->key = $key === null ? null : strtolower(trim($key));
    }

    public function getDefault(): ?string
    {
        return $this->default;
    }

    /**
     * Stored verbatim.
     *
     * The other setters normalise because they hold metadata the driver spells
     * inconsistently -- PRI, YES, auto_increment. This one holds a value that
     * gets written to the column whenever a model has nothing for it.
     */

    public function setDefault(?string $default): void
    {
        $this->default = $default;
    }

    public function getExtra(): ?string
    {
        return $this->extra;
    }

    public function setExtra(?string $extra): void
    {
        $this->extra = $extra === null ? null : strtolower(trim($extra));
    }

    public function isPrimaryKey(): bool
    {
        return $this->key === 'pri';
    }

    public function isAcceptableNull(): bool
    {
        return $this->null === 'yes';
    }

    public function isAutoIncrement(): bool
    {
        return $this->extra !== null && str_contains($this->extra, 'auto_increment');
    }

    /**
     * Whether the default is an expression the database evaluates rather than a
     * fixed value -- CURRENT_TIMESTAMP, GETDATE(), (concat('a','b')).
     *
     * There is nothing to bind for such a column, so the DAO leaves it out of
     * the statement and lets the database apply it. Each driver reports this
     * differently, so the driver decides rather than this class guessing.
     */
    public function isGeneratedDefault(): bool
    {
        return $this->generatedDefault;
    }

    public function setGeneratedDefault(bool $generatedDefault): void
    {
        $this->generatedDefault = $generatedDefault;
    }
}
