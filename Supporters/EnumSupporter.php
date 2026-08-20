<?php
/**
 * EnumSupporter
 *
 * Flexagon : PHP Development Framework
 *
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */
namespace _Flexagon\Supporters;

/**
 * Convenience accessors for enums.
 *
 * Written for backed enums. A pure enum has no backing value, so the value
 * based methods fall back to the case name where a string is expected and
 * report null where a value is expected.
 */
trait EnumSupporter
{
	/**
	 * Case values joined by a comma, e.g. 'ADMIN,USER'.
	 *
	 * Values are not quoted. Use toQuotedCommaString() when the result goes
	 * into SQL, such as an ENUM column definition.
	 */
	public static function toCommaString(): string {
		return implode(',', static::values(true));
	}

	/**
	 * Case values quoted one by one, e.g. "'ADMIN','USER'".
	 *
	 * Doubling the quote character is how both SQL and T-SQL escape it, so the
	 * result is safe to paste into an ENUM column definition even when a case
	 * value contains a quote or a comma.
	 *
	 * @param string $quote quote character to wrap each value in
	 */
	public static function toQuotedCommaString(string $quote = "'"): string {
		return implode(',', array_map(
			fn($value) => $quote . str_replace($quote, $quote . $quote, (string)$value) . $quote,
			static::values(true)
		));
	}

	/**
	 * Case name => case value.
	 */
	public static function toArray(): array {
		return array_combine(static::keys(), static::values());
	}

	/**
	 * Case names, in declaration order.
	 */
	public static function keys(): array {
		return array_map(fn($case) => $case->name, static::cases());
	}

	/**
	 * Case values, in declaration order.
	 *
	 * @param bool $nameForPureEnum fall back to the case name instead of null
	 *                              when the enum has no backing value
	 */
	public static function values(bool $nameForPureEnum = false): array {
		return array_map(
			fn($case) => $case->value ?? ($nameForPureEnum ? $case->name : null),
			static::cases()
		);
	}
}
