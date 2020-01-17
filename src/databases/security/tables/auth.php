<?php
namespace carlonicora\minimalism\databases\security\tables;

use carlonicora\minimalism\database\abstractDatabaseManager;
use carlonicora\minimalism\exceptions\dbRecordNotFoundException;
use carlonicora\minimalism\interfaces\securitySessionInterface;
use RuntimeException;
use Exception;

class auth extends abstractDatabaseManager implements securitySessionInterface {
    protected $fields = [
        'authId'=>self::INTEGER+self::PRIMARY_KEY+self::AUTO_INCREMENT,
        'userId'=>self::INTEGER,
        'clientId'=>self::INTEGER,
        'expirationDate'=>self::STRING,
        'publicKey'=>self::STRING,
        'privateKey'=>self::STRING];

    /**
     * @param string $publicKey
     * @param string $clientId
     * @return string
     * @throws Exception
     */
    public function getPrivateKey(string $publicKey, string $clientId): string {
        try {
            $auth = $this->loadFromPublicKeyAndClientId($publicKey, $clientId);
        } catch (dbRecordNotFoundException $e) {
            throw new RuntimeException('Record not found', 1);
        }

        if (time() > strtotime($auth['expirationDate']) ) {
            throw new RuntimeException('Session expired', 2);

        }

        return $auth['privateKey'];
    }

    /**
     * @param $publicKey
     * @param $clientId
     * @return array|null
     * @throws dbRecordNotFoundException
     */
    public function loadFromPublicKeyAndClientId($publicKey, $clientId): ?array {
        $sql = 'SELECT auth.* FROM auth JOIN clients ON auth.clientId=clients.id WHERE auth.publicKey=? AND clients.clientId=?;';
        $parameters = ['ss', $publicKey, $clientId];

        return $this->runReadSingle($sql, $parameters);
    }

    /**
     * @param $publicKey
     * @return array|null
     * @throws dbRecordNotFoundException
     */
    public function loadFromPublicKey($publicKey): ?array {
        $sql = 'SELECT * FROM auth WHERE publicKey = ?;';
        $parameters = ['s', $publicKey];

        return $this->runReadSingle($sql, $parameters);
    }

    /**
     * @return bool
     */
    public function deleteOldTokens(): bool {
        $sql = 'DELETE FROM auth WHERE expirationDate < ?;';
        $parameters = ['s', date('Y-m-d H:i:s')];

        return $this->runSql($sql, $parameters);
    }

    /**
     * @param $publicKey
     * @return bool
     */
    public function deleteFromPublicKey($publicKey): bool {
        $sql = 'DELETE from auth WHERE publicKey = ?;';
        $parameters = ['s', $publicKey];

        return $this->runSql($sql, $parameters);
    }
}