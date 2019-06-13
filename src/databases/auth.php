<?php
namespace carlonicora\minimalism\databases;

use carlonicora\minimalism\abstracts\databaseManager;

class auth extends databaseManager
{
    protected $dbToUse = 'minimalism';
    protected $fields = [
        'authId'=>self::PARAM_TYPE_INTEGER,
        'userId'=>self::PARAM_TYPE_INTEGER,
        'clientId'=>self::PARAM_TYPE_INTEGER,
        'expirationDate'=>self::PARAM_TYPE_STRING,
        'publicKey'=>self::PARAM_TYPE_STRING,
        'privateKey'=>self::PARAM_TYPE_STRING];

    protected $primaryKey = [
        'authId'=>self::PARAM_TYPE_INTEGER];

    protected $autoIncrementField = 'authId';

    public function loadFromPublicKeyAndClientId($publicKey, $clientId){
        $sql = 'SELECT * FROM auth WHERE publicKey = ? AND clientId = ?;';
        $parameters = ['si', $publicKey, $clientId];

        $response = $this->runReadSingle($sql, $parameters);

        return($response);
    }

    public function deleteOldTokens(){
        $sql = 'DELETE FROM auth WHERE expirationDate < ?;';
        $parameters = ['s', date('Y-m-d H:i:s', time())];

        $response = $this->runSql($sql, $parameters);

        return($response);
    }
}