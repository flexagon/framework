<?php
/**
 * Encrypt
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
 * Encrypt this property on the way to the database and decrypt it on the way
 * back, using _Global::$CLASS_PROPERTY_ENCRYPTION_PASSPHRASE.
 *
 *     #[Encrypt]
 *     private string $ssn = '';
 *
 * The same thing as the @encrypt doc block tag, which still works. This form
 * is what an IDE can complete, jump to and rename, and it survives
 * opcache.save_comments = 0.
 *
 * The column has to be wide enough for the ciphertext, which is longer than
 * the plain value.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Encrypt
{
}
