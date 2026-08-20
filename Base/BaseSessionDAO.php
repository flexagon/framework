<?php
/**
 * BaseSessionDAO
 *
 * Stateless session stored in an encrypted cookie.
 *
 * Flexagon : PHP Development Framework
 *
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */
namespace _Flexagon\Base;

use _Flexagon\Libs\CryptoUtil;
use Exception;

abstract class BaseSessionDAO {
    private string $domain = '';
	private string $sessionName = 'session_data';
	private ?object $sessionModel = null;
	private ?string $encryptionString = null;
	private ?string $encryptionKey = null;
	private int $sessionTimeout = 86400;

    /**
     * @throws Exception
     */
    function __construct() {
        if ( isset(\_Global::$SESSION_DOMAIN) ) {
            $this->domain = \_Global::$SESSION_DOMAIN;
        } else {
            throw new Exception( '[FLEXAGON] _Global::$SESSION_DOMAIN be required.');
        }

		if ( empty(\_Global::$SESSION_ENCRYPTION_STRING) || strlen(\_Global::$SESSION_ENCRYPTION_STRING) < \FLEXAGON_CONST::CIPHER_PASSPHRASE_MIN_LENGTH ) {
			throw new Exception('[FLEXAGON] _Global::$SESSION_ENCRYPTION_STRING must needs to be minimum '.\FLEXAGON_CONST::CIPHER_PASSPHRASE_MIN_LENGTH.' chars long.');
		}
		$this->setEncryptionKey(\_Global::$SESSION_ENCRYPTION_STRING);

        if ( !empty(\_Global::$SESSION_NAME) ) {
            $this->sessionName = \_Global::$SESSION_NAME;
        }

        if ( !empty(\_Global::$SESSION_TIMEOUT_SECONDS) ) {
            $this->sessionTimeout = \_Global::$SESSION_TIMEOUT_SECONDS;
        }
	}

    /**
     * Make user session by SessionModel
     *
     * @param object $sessionModel
     * @return boolean
     * @throws Exception
     */
	public function makeSession(object $sessionModel): bool
    {
		$issuedAt = time();
		$sessionTimeoutTimestamp = $issuedAt + $this->sessionTimeout;
		if ( $this->sessionTimeout == 0 ) $sessionTimeoutTimestamp = 0;

		$sessionData = [
			'name'  => get_class($sessionModel),
			'value' => $sessionModel->getJson(),
			'iat'   => $issuedAt,
			'exp'   => $sessionTimeoutTimestamp,
		];
		$enString = CryptoUtil::encrypt($sessionData,$this->encryptionKey);

		setcookie($this->sessionName, $enString, $this->getCookieOptions($sessionTimeoutTimestamp));
		return true;
	}

	public function cleanSession(): void {
		setcookie($this->sessionName, '', $this->getCookieOptions(time()-3600));
		\_Global::$SESSION_MODEL = null;
		$this->sessionModel = null;
	}

	/**
	 *
	 * @return boolean
	 */
	public function isSignedIn(): bool
    {
		if ( isset($this->sessionModel) && !empty($this->sessionModel) ) return true;

		try {
			return !empty($this->getSessionModel());
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Cookie attributes for the session cookie.
	 *
	 * The session carries the signed-in identity, so it goes out with HttpOnly
	 * and SameSite set, and with Secure whenever the request is over HTTPS.
	 *
	 * @param int $expires
	 * @return array
	 */
	protected function getCookieOptions(int $expires): array {
		$secure = \_Global::$SESSION_COOKIE_SECURE;
		if ( is_null($secure) ) { $secure = self::isSecureRequest(); }

		$sameSite = ucfirst(strtolower(trim(\_Global::$SESSION_COOKIE_SAMESITE)));
		if ( $sameSite !== '' && !in_array($sameSite, ['Lax', 'Strict', 'None'], true) ) { $sameSite = 'Lax'; }
		if ( $sameSite === 'None' ) { $secure = true; }

		$options = [
			'expires'  => $expires,
			'path'     => '/',
			'domain'   => $this->domain,
			'secure'   => (bool)$secure,
			'httponly' => \_Global::$SESSION_COOKIE_HTTPONLY,
		];

		if ( $sameSite !== '' ) { $options['samesite'] = $sameSite; }

		return $options;
	}

	/**
	 * Check the time bounds carried inside the decrypted payload.
	 *
	 * @param array $sessionArray
	 * @return bool
	 */
	protected function isSessionPayloadValid(array $sessionArray): bool
	{
		$issuedAt = isset($sessionArray['iat']) ? (int)$sessionArray['iat'] : 0;

		if ( \_Global::$SESSION_NOT_BEFORE > 0 && $issuedAt < \_Global::$SESSION_NOT_BEFORE ) {
			return false;
		}

		if ( !array_key_exists('exp', $sessionArray) ) {
			return !\_Global::$SESSION_REQUIRE_EXPIRY;
		}

		$expiresAt = (int)$sessionArray['exp'];

		if ( $expiresAt > 0 && $expiresAt <= time() ) {
			return false;
		}

		return true;
	}

	/**
	 * Drop a cookie the server has just refused, so the browser stops sending
	 * it on every subsequent request.
	 *
	 * @return void
	 */
	protected function discardSessionCookie(): void
	{
		unset($_COOKIE[$this->sessionName]);

		if ( headers_sent() ) { return; }
		setcookie($this->sessionName, '', $this->getCookieOptions(time()-3600));
	}

	/**
	 * Decide whether the class named in the cookie may be instantiated.
	 *
	 * @param string $className
	 * @return bool
	 */
	protected static function isAllowedSessionModel(string $className): bool {
		$className = ltrim(trim($className), '\\');
		if ( $className === '' ) {
			return false;
		}

		$allowedClasses = \_Global::$SESSION_MODEL_CLASSES;
		if ( !empty($allowedClasses) ) {
			$normalized = array_map(fn($allowed) => ltrim(trim((string)$allowed), '\\'), $allowedClasses);
			if ( !in_array($className, $normalized, true) ) {
				return false;
			}
		}

		if ( !class_exists($className) ) {
			return false;
		}

		 
		return is_subclass_of($className, BaseModel::class);
	}

	/**
	 * @return bool
	 */
	protected static function isSecureRequest(): bool {
		if ( !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off' ) { return true; }
		if ( (int)($_SERVER['SERVER_PORT'] ?? 0) === 443 ) { return true; }
		if ( strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https' ) { return true; }
		return false;
	}

	protected function setSessionDomain($sessionDomain): void {
		$this->domain = $sessionDomain;
	}

    /**
     * @throws Exception
     */
    protected function setEncryptionKey($encryptionKey): void {
		$encryptionKey = trim((string)$encryptionKey);
		if ( empty($encryptionKey) || strlen($encryptionKey) < \FLEXAGON_CONST::CIPHER_PASSPHRASE_MIN_LENGTH ) {
			throw new Exception('[FLEXAGON] An encryption key must needs to be minimum'.\FLEXAGON_CONST::CIPHER_PASSPHRASE_MIN_LENGTH.' chars long.');
		}

		$this->encryptionKey = $encryptionKey;
	}

	/**
	 * @param int $sessionTimeout Timeout Sec.
	 */
	public function setSessionTimeout(int $sessionTimeout): void {
		$this->sessionTimeout = $sessionTimeout;
	}

    /**
     * @return object|null
     * @throws Exception
     */
    public function getSessionModel(): ?object
    {
		if ( !isset($_COOKIE[$this->sessionName]) ) { $this->sessionModel = null; }
		else {
			$sessionArray = CryptoUtil::decrypt($_COOKIE[$this->sessionName], $this->encryptionKey);

			if ( !is_array($sessionArray) ) {
				$this->sessionModel = null;
				return null;
			}

			if ( !$this->isSessionPayloadValid($sessionArray) ) {
				$this->sessionModel = null;
				$this->discardSessionCookie();
				return null;
			}

            $className = null;
			if ( isset($sessionArray['name'])) {
                $className = $sessionArray['name'];
            }
			if ( isset($sessionArray['value']) ) {
                if ( is_array($sessionArray['value'])) {
                    $classValue = $sessionArray['value'];
                } else {
                    $classValue = json_decode($sessionArray['value'],true);
                }
            }

			if (is_null($this->sessionModel)) {
				if (!empty($className) && self::isAllowedSessionModel($className)) {
					$this->sessionModel = new $className();
					$this->sessionModel->setByArray($classValue);
				}
			}
		}
		return $this->sessionModel;
	}
}
