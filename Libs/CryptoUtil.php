<?php
/**
 * CryptoUtil
 *
 * Flexagon : PHP Development Framework
 *
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */
namespace _Flexagon\Libs;


use Exception;

class CryptoUtil
{
    /**
     * Leading byte of the encryptString() payload. Lets the format be
     * recognised, and rejected when it is not what this build produces.
     */
    private const CIPHER_FORMAT_VERSION = "\x01";

    /**
     * SHA-256 output length, in bytes.
     */
    private const HMAC_LENGTH = 32;

    private static string $randString = '';

    /**
     * @param string $passPhrase
     * @return void
     * @throws Exception
     */
    private static function assertPassPhrase(string $passPhrase): void
    {
        if ( strlen($passPhrase) < \FLEXAGON_CONST::CIPHER_PASSPHRASE_MIN_LENGTH ) {
            throw new Exception('$passPhrase must have minimum '.\FLEXAGON_CONST::CIPHER_PASSPHRASE_MIN_LENGTH.' bytes string.');
        }
    }

    /**
     * Stretch the passphrase into a full-length key.
     *
     * The passphrase is a human-chosen string of arbitrary length; handing it
     * to openssl_encrypt() directly would use those bytes as the AES key and
     * zero-pad the remainder. Separate keys are derived for encryption and for
     * authentication so that one never doubles as the other.
     *
     * @param string $passPhrase
     * @param string $purpose 'enc' or 'mac'
     * @return string 32 raw bytes
     */
    private static function deriveKey(string $passPhrase, string $purpose): string
    {
        return hash_hkdf('sha256', $passPhrase, 32, 'flexagon:' . $purpose . ':v1');
    }


    /**
     * @param mixed $source
     * @param string $passPhrase
     * @return string|null
     * @throws Exception
     */
    public static function encrypt(mixed $source, string $passPhrase = '' ): ?string {
        $encryptedStr = null;
        if ( is_array($source) ) {
            $encryptedStr = 'a'.self::encryptArrayWithRand($source, $passPhrase);
        } elseif ( is_string($source) ) {
            $encryptedStr = 's'.self::encryptStringWithRand($source, $passPhrase);
        }
        return $encryptedStr;
    }

    /**
     * @param string $encryptedString
     * @param string $passPhrase
     * @return mixed
     * @throws Exception
     */
    public static function decrypt(string $encryptedString, string $passPhrase = '' ): mixed {
        $decrypted = null;
        $type = substr($encryptedString, 0, 1);
        $string = substr($encryptedString, 1);
        if ( $type == 'a' ) {
            $decrypted = self::decryptArrayWithRand($string, $passPhrase);
        } elseif ( $type == 's' ) {
            $decrypted = self::decryptStringWithRand($string, $passPhrase);
        }
        return $decrypted;
    }

    /**
     * Encrypt a string.
     * @param string|null $string
     * @param string $passPhrase
     * @param string $cipherMethod
     * @return string
     * @throws Exception
     */
    public static function encryptString(?string $string, string $passPhrase = '', string $cipherMethod = '' ): string
    {
        if ( is_null($string) || $string === '' ) { return ''; }

        $passPhrase = trim($passPhrase);
        $cipherMethod = trim($cipherMethod);
        if ( empty($cipherMethod) ) { $cipherMethod = \FLEXAGON_CONST::CIPHER_METHOD; }
        self::assertPassPhrase($passPhrase);

        $ivLength = openssl_cipher_iv_length($cipherMethod);
        if ( $ivLength === false ) {
            throw new Exception(sprintf('Unknown cipher method "%s".', $cipherMethod));
        }

        $iv = $ivLength > 0 ? random_bytes($ivLength) : '';

        $cipherText = openssl_encrypt($string, $cipherMethod, self::deriveKey($passPhrase, 'enc'), OPENSSL_RAW_DATA, $iv);
        if ( $cipherText === false ) {
            throw new Exception('Encryption failed.');
        }

         
        $hmac = hash_hmac('sha256', self::CIPHER_FORMAT_VERSION . $iv . $cipherText, self::deriveKey($passPhrase, 'mac'), true);

        return base64_encode(self::CIPHER_FORMAT_VERSION . $iv . $hmac . $cipherText);
    }

    /**
     * @param string|null $encryptedString
     * @param string $passPhrase
     * @param string|null $cipherMethod
     * @return string
     * @throws Exception
     */
    public static function decryptString(?string $encryptedString, string $passPhrase = '' , ?string $cipherMethod = ''): string
    {
        if ( is_null($encryptedString) || $encryptedString === '' ) { return ''; }

        $passPhrase = trim((string)$passPhrase);
        $cipherMethod = trim((string)$cipherMethod);
        if ( empty($cipherMethod) ) { $cipherMethod = \FLEXAGON_CONST::CIPHER_METHOD; }
        self::assertPassPhrase($passPhrase);

        $raw = base64_decode($encryptedString, true);
        if ( $raw === false ) { return ''; }

        $ivLength = openssl_cipher_iv_length($cipherMethod);
        if ( $ivLength === false ) { return ''; }

        $headerLength = 1 + $ivLength + self::HMAC_LENGTH;
        if ( strlen($raw) <= $headerLength ) { return ''; }

        $version = substr($raw, 0, 1);
        if ( !hash_equals(self::CIPHER_FORMAT_VERSION, $version) ) { return ''; }

        $iv         = substr($raw, 1, $ivLength);
        $hmac       = substr($raw, 1 + $ivLength, self::HMAC_LENGTH);
        $cipherText = substr($raw, $headerLength);

         
        $expectedHmac = hash_hmac('sha256', $version . $iv . $cipherText, self::deriveKey($passPhrase, 'mac'), true);
        if ( !hash_equals($expectedHmac, $hmac) ) { return ''; }

        $plainText = openssl_decrypt($cipherText, $cipherMethod, self::deriveKey($passPhrase, 'enc'), OPENSSL_RAW_DATA, $iv);
        return $plainText === false ? '' : $plainText;
    }

    /**
     * Encrypt a string with a random salt, for the session cookie format.
     * @param string $string
     * @param string $passPhrase
     * @param string $cipherMethod
     * @return string
     * @throws Exception
     */
    private static function encryptStringWithRand(string $string, string $passPhrase = '', string $cipherMethod = '' ): string
    {
        $passPhrase = trim($passPhrase);
        $cipherMethod = trim($cipherMethod);

        if ( empty($cipherMethod) ) { $cipherMethod = \FLEXAGON_CONST::CIPHER_METHOD; }
        if ( empty($passPhrase) || strlen($passPhrase) < \FLEXAGON_CONST::CIPHER_PASSPHRASE_MIN_LENGTH ) {
            throw new Exception('$passPhrase must have minimum '.\FLEXAGON_CONST::CIPHER_PASSPHRASE_MIN_LENGTH.' bytes string.');
        }

        if ( empty(self::$randString) ) {
            CryptoUtil::$randString = StringUtil::generateRandomString(10);
        }
        $destPassPhrase = $passPhrase.self::$randString;

        $ivLength = openssl_cipher_iv_length($cipherMethod);
        $iv = openssl_random_pseudo_bytes($ivLength);
        $cipherTextRaw = openssl_encrypt($string, $cipherMethod, $destPassPhrase, $options=0, $iv);
        $hmac = hash_hmac('sha256', $cipherTextRaw, $destPassPhrase, $as_binary=true);

        if ( $hmac === false ) { return false; }
        return base64_encode( $iv.$hmac.self::$randString.$cipherTextRaw );
    }

    /**
     * @param $encryptedString
     * @param string $passPhrase
     * @param string $cipherMethod
     * @return string
     * @throws Exception
     */
    private static function decryptStringWithRand($encryptedString, string $passPhrase = '' , string $cipherMethod = ''): string {
        $passPhrase = trim((string)$passPhrase);
        $cipherMethod = trim((string)$cipherMethod);

        if ( empty($cipherMethod) ) { $cipherMethod = \FLEXAGON_CONST::CIPHER_METHOD; }
        if ( empty($passPhrase) || strlen($passPhrase) < \FLEXAGON_CONST::CIPHER_PASSPHRASE_MIN_LENGTH ) {
            throw new Exception('$passPhrase must have minimum '.\FLEXAGON_CONST::CIPHER_PASSPHRASE_MIN_LENGTH.' bytes string.');
        }
        $sha2Length=32;
        $randStrLength = 10;
        $c = base64_decode($encryptedString);
        $ivLength = openssl_cipher_iv_length($cipherMethod);
        $iv = substr($c, 0, $ivLength);
        $cipherTextRaw = substr($c, $ivLength+$sha2Length+$randStrLength);
        $randStr = substr($c, $ivLength+$sha2Length, $randStrLength);
        $destPassPhrase = $passPhrase.$randStr;
        $decryptedString = openssl_decrypt($cipherTextRaw, $cipherMethod, $destPassPhrase, $options=0, $iv);

        $hmac = substr($c, $ivLength, $sha2Length );
        $checkHmac = hash_hmac('sha256', $cipherTextRaw, $destPassPhrase, $as_binary=true);

        if ( hash_equals($checkHmac, $hmac) ) { return $decryptedString; }
        return '';
    }

    /**
     * @param array $array
     * @param string $passPhrase
     * @return string
     * @throws Exception
     */
    private static function encryptArrayWithRand(array $array, string $passPhrase = ''): string
    {
        $string = json_encode($array);
        return self::encryptStringWithRand($string, $passPhrase);
    }

    /**
     * @param string $encryptedString
     * @param string $passPhrase
     * @return mixed
     * @throws Exception
     */
    private static function decryptArrayWithRand(string $encryptedString, string $passPhrase = ''): mixed
    {
        $jsonString = self::decryptStringWithRand($encryptedString, $passPhrase);

        if ( !empty($jsonString) ) {
            return json_decode($jsonString,true);
        }
        return false;
    }
}