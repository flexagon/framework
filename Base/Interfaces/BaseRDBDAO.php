<?php
/**
 * BaseRDBDAO
 *
 * Transaction surface every relational DAO offers.
 *
 * Flexagon : PHP Development Framework
 *
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */
namespace _Flexagon\Base\Interfaces;

interface BaseRDBDAO
{
    public function startTransaction();
    public function commit();
    public function rollback();
}