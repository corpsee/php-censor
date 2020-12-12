<?php

namespace PHPCensor\Store;

use PDO;
use PHPCensor\Database;
use PHPCensor\Exception\HttpException;
use PHPCensor\Model\BuildRequest;
use PHPCensor\Store;

class BuildRequestStore extends Store
{
    /**
     * @var string
     */
    protected $tableName  = 'build_requests';

    /**
     * @var string
     */
    protected $modelName  = '\PHPCensor\Model\BuildRequest';

    /**
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Get a Build by primary key (Id)
     *
     * @param int $key
     * @param string  $useConnection
     *
     * @return BuildRequest|null
     */
    public function getByPrimaryKey($key, $useConnection = 'read')
    {
        return $this->getById($key, $useConnection);
    }

    /**
     * Get a single Build by Id.
     *
     * @param int    $id
     * @param string $useConnection
     *
     * @return BuildRequest|null
     *
     * @throws HttpException
     */
    public function getById($id, $useConnection = 'read')
    {
        if (\is_null($id)) {
            throw new HttpException('Value passed to ' . __FUNCTION__ . ' cannot be null.');
        }

        $query = 'SELECT * FROM {{' . $this->tableName . '}} WHERE {{id}} = :id LIMIT 1';
        $stmt = Database::getConnection($useConnection)->prepareCommon($query);
        $stmt->bindValue(':id', $id);

        if ($stmt->execute()) {
            if ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
                return new BuildRequest($data);
            }
        }

        return null;
    }
}
