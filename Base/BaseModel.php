<?php
/**
 * BaseModel
 *
 * Flexagon : PHP Development Framework
 *
 * @author        Younghwan Yong <young@phpk.org>
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */
namespace _Flexagon\Base;
use _Flexagon\Attributes\DbAutoTimestamp;
use _Flexagon\Libs\ArrayUtil;
use _Flexagon\Libs\CryptoUtil;
use _Flexagon\Libs\PhpDocUtil;
use Exception;
use JsonSerializable;
use ReflectionClass;
use ReflectionEnum;
use ReflectionException;
use ReflectionNamedType;
use TypeError;
use UnitEnum;

abstract class BaseModel implements JsonSerializable {
    /**
     * @var int|null
     * @db_auto_timestamp insert
     */
    #[DbAutoTimestamp('insert')]
    protected ?int $createdAt = null;

    /**
     * @var int|null
     * @db_auto_timestamp insert|update
     */
    #[DbAutoTimestamp('insert', 'update')]
    protected ?int $updatedAt = null;

    /**
     * @return ?int
     */
    public function getCreatedAt(): ?int
    {
        return $this->createdAt;
    }

    /**
     * @param ?int $createdAt
     * @return void
     */
    public function setCreatedAt(?int $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return string
     */
    public function getCreatedAtDatetimeString(): string
    {
        if ( !empty($this->getCreatedAt())) {
            return date("Y-m-d H:i:s", $this->getCreatedAt());
        }
        return '';
    }


    /**
     * @return ?int
     */
    public function getUpdatedAt(): ?int
    {
        return $this->updatedAt;
    }

    /**
     * @param int|null $updatedAt
     * @return void
     */
    public function setUpdatedAt(?int $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    /**
     * @return string
     */
    public function getUpdatedAtDatetimeString(): string
    {
        if ( !empty($this->getCreatedAt())) {
            return date("Y-m-d H:i:s", $this->getUpdatedAt());
        }
        return '';
    }

    /**
     * @param bool $containsObject
     * @param bool $forceAll
     * @return array
     */
	public function getArray(bool $containsObject = true, bool $forceAll = false): array
    {
		$methodList = get_class_methods(get_class($this));
		$varList = [];
		foreach ( $methodList as $methodName ) {
			if ( !preg_match('/^(?:get|is)([A-Z].*)/',$methodName,$matches) ) { continue; }

			$functionName = $matches[0];
			if ( $functionName == 'getJson' || $functionName == 'getArray' ) { continue; }

			if ( !$forceAll && PhpDocUtil::existsMethodTag($this, $functionName, \FLEXAGON_CONST::ANNOTATION_TAG_CLASS_EXCLUDE_FROM_GET_ARRAY ) ) { continue; }

			$tempResult = call_user_func([$this,$functionName]);
			if ( !$containsObject  ) {
				if ($tempResult instanceof UnitEnum && $tempResult->value) {
					$tempResult = $tempResult->value;
				} elseif (is_object($tempResult) && method_exists($tempResult, 'getArray')) {
					$tempResult = $tempResult->getArray(false);
				}
			}
			$varList[lcfirst($matches[1])] = $tempResult;
		}
		return $varList;
	}

    /**
     * @param $keyValueArray
     */
	public function setByArray($keyValueArray): void
    {
        $keyValueArray = ArrayUtil::convertArrayKeyToCamelcase($keyValueArray);
		$methodList = get_class_methods(get_class($this));
		foreach ( $methodList as $methodName ) {
			if ( preg_match('/^set([A-Z].*)/',$methodName,$matches) ) {
				$functionName = $matches[0];
				if ( $functionName != 'setByArray' ) {
					$varName = lcfirst($matches[1]);
					if ( array_key_exists($varName, $keyValueArray) ) {
                        try {
                            $this->$functionName($keyValueArray[$varName]);
                        } catch ( TypeError ) {
                            try {
                                $this->$functionName($this->coerceSetterValue($functionName, $keyValueArray[$varName]));
                            } catch (ReflectionException|TypeError) {
                            }
						}
                    }
				}
			}
		}
	}

    /**
     * A setter can turn down the raw value: an empty string where null is meant,
     * a null where the declared default is meant, or a scalar where an enum case
     * is meant. Translate the value so setByArray() can retry it once.
     *
     * Comes back untouched when none of those apply, in which case the retry
     * raises the original TypeError and setByArray() drops the value.
     *
     * @throws ReflectionException when the setter cannot be reflected on
     */
    private function coerceSetterValue(string $functionName, mixed $value): mixed
    {
        $reflectionParams = (new ReflectionClass($this))->getMethod($functionName)->getParameters();
        if ( empty($reflectionParams) ) { return $value; }

        $param = $reflectionParams[0];

        if ( $param->allowsNull() && $value === '' ) { return null; }
        if ( !$param->allowsNull() && is_null($value) && $param->isDefaultValueAvailable() ) { return $param->getDefaultValue(); }

         
        $paramType = $param->getType();
        if ( !$paramType instanceof ReflectionNamedType || $paramType->isBuiltin() ) { return $value; }

        try {
            $reflectionEnum = new ReflectionEnum($paramType->getName());
             
             
            if ( !$reflectionEnum->isBacked() ) { return $value; }

            foreach ( $reflectionEnum->getCases() as $enumCase ) {
                if ( $enumCase->getBackingValue() == $value ) { return $enumCase->getValue(); }
            }
        } catch ( ReflectionException ) {
        }

        return $value;
    }

    /**
     * @param int $jsonFlags [optional] <p>
     * Bitmask consisting of <b>JSON_HEX_QUOT</b>,
     * <b>JSON_HEX_TAG</b>,
     * <b>JSON_HEX_AMP</b>,
     * <b>JSON_HEX_APOS</b>,
     * <b>JSON_NUMERIC_CHECK</b>,
     * <b>JSON_PRETTY_PRINT</b>,
     * <b>JSON_UNESCAPED_SLASHES</b>,
     * <b>JSON_FORCE_OBJECT</b>,
     * <b>JSON_UNESCAPED_UNICODE</b>.
     * <b>JSON_THROW_ON_ERROR</b> The behaviour of these
     * constants is described on
     * the JSON constants page.
	 *
	 * Objects are not included.
     * </p>
     * @return string|null
     */
	public function getJson(int $jsonFlags = JSON_UNESCAPED_UNICODE): ?string {
		return json_encode($this->getArray(false), $jsonFlags);
	}

    public function jsonSerialize(): array {
        return $this->getArray();
    }

    /**
     * @throws Exception
     */
    private function checkEncryptionPassphrase(): bool
    {
        if ( empty(\_Global::$CLASS_PROPERTY_ENCRYPTION_PASSPHRASE) || strlen(\_Global::$CLASS_PROPERTY_ENCRYPTION_PASSPHRASE) < \FLEXAGON_CONST::CIPHER_PASSPHRASE_MIN_LENGTH ) {
            throw new Exception('\_Global::$CLASS_PROPERTY_ENCRYPTION_PASSPHRASE must have minimum '.\FLEXAGON_CONST::CIPHER_PASSPHRASE_MIN_LENGTH.' bytes string.');
        }
        return true;
    }

    /**
     * @throws Exception
     */
    public function encrypt(): bool {
        $properties = PhpDocUtil::findProperties($this, \FLEXAGON_CONST::ANNOTATION_TAG_CLASS_PROPERTY_ENCRYPTION);

        if ( !empty($properties) ) {
            self::checkEncryptionPassphrase();

            $propertiesArray = $this->getArray();
            foreach ($properties as $property) {
                try {
                    if ( is_string($propertiesArray[$property])) {
                        $propertiesArray[$property] = CryptoUtil::encryptString($propertiesArray[$property], \_Global::$CLASS_PROPERTY_ENCRYPTION_PASSPHRASE);
                    }
                } catch ( Exception $e ) {
                    return false;
                }
            }
            $this->setByArray($propertiesArray);
        }
        return true;
    }

    /**
     * @throws Exception
     */
    public function decrypt(): bool {
        $properties = PhpDocUtil::findProperties($this, \FLEXAGON_CONST::ANNOTATION_TAG_CLASS_PROPERTY_ENCRYPTION);

         
        if ( !empty($properties) ) {
            self::checkEncryptionPassphrase();

            $propertiesArray = $this->getArray();

            foreach ($properties as $property) {
                $tempVal= '';
                try {
                    if ( is_string($propertiesArray[$property])) {
                        $tempVal = CryptoUtil::decryptString($propertiesArray[$property], \_Global::$CLASS_PROPERTY_ENCRYPTION_PASSPHRASE);
                    }
                } catch ( Exception $e ) {
                    return false;
                }

                if ( empty($tempVal) && !empty($propertiesArray[$property]) ) {
                } else {
                    $propertiesArray[$property] = $tempVal;
                }
            }
            $this->setByArray($propertiesArray);
        }
        return true;
    }
}