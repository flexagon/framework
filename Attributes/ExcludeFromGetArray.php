<?php
/**
 * ExcludeFromGetArray
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
 * Keep this getter out of getArray() and getJson().
 *
 *     #[ExcludeFromGetArray]
 *     public function getPasswordHash(): string { ... }
 *
 * The same thing as the @exclude_from_get_array doc block tag, which still
 * works. Serialisation only: the DAO reads columns straight off the model, so
 * an excluded getter is still written to and read from the database.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class ExcludeFromGetArray
{
}
