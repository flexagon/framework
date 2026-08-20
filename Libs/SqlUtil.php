<?php
/**
 * SqlUtil
 *
 * Flexagon : PHP Development Framework
 *
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */
namespace _Flexagon\Libs;

class SqlUtil
{
	public static function convertArrayToSqlParams(?array $params = null): array
	{
		$newParams = [];

		if (empty($params)) {
			return $newParams;
		}

		foreach ($params as $key => $value) {
			if (is_string($key)) {
				if ($key[0] !== ':') {
					$key = ':' . $key;
				}
			}

            if ( $value instanceof \BackedEnum ) {
                $value = $value->value;
            } elseif ( $value instanceof \UnitEnum ) {
                $value = null;
            }

            if ( is_bool($value) ) {
                $value = (int)$value;
            }

			$newParams[$key] = $value;
		}

		return $newParams;
	}

	public static function expandQueryParams(string $query, ?array $params = null): array
	{
		if (!is_array($params)) {
			return [$query, []];
		}

		$isAssocArray = array_keys($params) !== range(0, count($params) - 1);

		if (!$isAssocArray) {
			return [$query, $params];
		}

		$paramCounts = [];
		$expandedParams = [];

		$newQuery = preg_replace_callback('/:([a-zA-Z_][a-zA-Z0-9_]*)/', function ($matches) use (&$paramCounts, $params, &$expandedParams) {
			$param = $matches[1];

			if (!array_key_exists($param, $params)) {
				return $matches[0];
			}

			$count = $paramCounts[$param] = ($paramCounts[$param] ?? 0) + 1;

			if ($count === 1) {
				$expandedParams[$param] = $params[$param];
				return ":{$param}";
			}

			$newParam = sprintf('__exp_%s__%d', $param, $count - 1);
			$expandedParams[$newParam] = $params[$param];
			return ":{$newParam}";
		}, $query);

		return [$newQuery, $expandedParams];
	}

	public static function combineQueryParams(?string $query, ?array $params = null): string {
		if ($query === null) {
			return '';
		}

		if (empty($params)) {
			return $query;
		}

		return preg_replace_callback('/:([_a-zA-Z][_a-zA-Z0-9]*)/', function ($matches) use ($params) {
			$key = $matches[1];

			if (!array_key_exists($key, $params)) {
				return $matches[0];
			}

			$value = $params[$key];

			switch (gettype($value)) {
				case 'integer':
				case 'double':
					return (string)$value;
                case 'object':
                    if ( $value instanceof \UnitEnum ) {
                        return $value->value;
                    }
                    break;
				case 'string':
					return '"' . SqlUtil::escapeMysqlString($value) . '"';
				case 'boolean':
					return $value ? 'TRUE' : 'FALSE';
				case 'NULL':
					return 'NULL';
				default:
					return 'NULL';
			}
		}, $query);
	}

	public static function escapeMysqlString(?string $value): string {
		if ($value === null) {
			return '';
		}

		static $search  = ["\\",  "\x00", "\n",  "\r",  "'",  '"', "\x1a"];
		static $replace = ["\\\\","\\0","\\n", "\\r", "\\'", '\\"', "\\Z"];

		return str_replace($search, $replace, $value);
	}


	public static function normalizeSql(string $sql): string {
		$pattern = '/([\'"])(?:\\\\.|[^\\\\])*?\1/s';  
		preg_match_all($pattern, $sql, $matches, PREG_OFFSET_CAPTURE);

		$output = '';
		$lastPos = 0;

		foreach ($matches[0] as [$strLiteral, $pos]) {
			$before = substr($sql, $lastPos, $pos - $lastPos);
			$before = preg_replace('/[\n\r\t]+/', ' ', $before);

			 
			$output .= $before . $strLiteral;
			$lastPos =(int)$pos + strlen($strLiteral);
		}

		 
		$after = substr($sql, $lastPos);
		$after = preg_replace('/[\n\r\t]+/', ' ', $after);
		$output .= $after;

		return $output;
	}

	public static function convertSelectToCountQuery(string $sql): string {
		if (!preg_match('/^SELECT\s/i', $sql)) {
			return '';
		}

		$sql = preg_replace('/--.*?(\n|$)/', '', $sql);
		$sql = preg_replace('/\/\*[\s\S]*?\*\//', '', $sql);

		$sql = preg_replace('/[\r\n\t]+/', ' ', $sql);
		$sql = preg_replace('/\s+/', ' ', $sql);
		$sql = trim($sql);

		$sql = preg_replace('/\s+LIMIT\s+\d+(\s*,\s*\d+)?\s*$/i', '', $sql);
		$sql = preg_replace('/\s+OFFSET\s+\d+\s*$/i', '', $sql);

		$sql = preg_replace_callback('/(.*?)(\sORDER\s+BY\s+[^)]*)(\s+LIMIT|\s+OFFSET|\s*$)/i', function ($m) {
			return $m[1] . $m[3];
		}, $sql);

		$unionPos = stripos($sql, 'UNION');
		$firstBlock = $unionPos !== false ? substr($sql, 0, $unionPos) : $sql;

		if (preg_match('/^SELECT\s+(DISTINCT\s+)?(.+?)\s+FROM\s+/i', $firstBlock, $matches, PREG_OFFSET_CAPTURE)) {
			$isDistinct = isset($matches[1]) && trim($matches[1][0]) === 'DISTINCT';
			$selectExpr = trim($matches[2][0]);
			$fromPos = $matches[0][1] + strlen($matches[0][0]);

			$countExpr = $isDistinct ? "COUNT(DISTINCT $selectExpr)" : "COUNT(*)";

			$countSql = "SELECT $countExpr FROM " . substr($firstBlock, $fromPos);
			return trim($countSql);
		}

		return 'SELECT COUNT(*) FROM (' . $sql . ') AS sub';
	}
}