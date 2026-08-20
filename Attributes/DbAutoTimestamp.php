<?php
/**
 * DbAutoTimestamp
 *
 * Flexagon : PHP Development Framework
 *
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */
namespace _Flexagon\Attributes;

use Attribute;

/**
 * Let the DAO fill this property with the current Unix timestamp.
 *
 *     #[DbAutoTimestamp('insert')]            only when the row is created
 *     #[DbAutoTimestamp('insert', 'update')]  and every time it is written
 *
 * The same thing as the @db_auto_timestamp doc block tag, which still works.
 *
 * @param string ...$on 'insert', 'update', or both
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class DbAutoTimestamp
{
    /** @var string[] */
    public readonly array $on;

    public function __construct(string ...$on)
    {
        $this->on = $on;
    }
}
