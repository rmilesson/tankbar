<?php
/**
 * Database class
 *
 * @package Database
 */

namespace TBDB\Database;

use TBDB\Exceptions\InvalidConversionException;
use TBDB\Exceptions\InvalidOptionException;
use TBDB\Exceptions\ArgumentException;

/**
 * Database
 */
class Database
{
    /**
     * The driver to use
     *
     * @var string $driver The driver to use.
     */
    private static $driver = 'mysql';

    /**
     * The host.
     *
     * @var string $host The host.
     */
    private static $host = 'localhost';

    /**
     * The port.
     *
     * @var int $port The port.
     */
    private static $port = 3306;

    /**
     * The database name to connect to.
     *
     * @var string $dbname The database name to connect to.
     */
    private static $dbname = '';

    /**
     * The charset to use.
     *
     * @var string $charset The charset to use.
     */
    private static $charset = 'utf8';

    /**
     * The user to connect with.
     *
     * @var string $user The user to connect with.
     */
    private static $user = '';

    /**
     * The password.
     *
     * @var string $password The password.
     */
    private static $password = '';

    /**
     * The current instance.
     *
     * @var Database $instance The instance.
     */
    private static $instance;

    /**
     * The current connection.
     *
     * @var PDO $connection The connection.
     */
    private $connection = null;

    const DBTYPE_STRING     = 100;
    const DBTYPE_NUMBER     = 200;
    const DBTYPE_FLOAT      = 300;
    const DBTYPE_SERIALIZED = 400;
    const DBTYPE_JSON       = 500;
    const DBTYPE_BOOL       = 600;

    /**
     * Initializes the Database class
     *
     * @param Array $options The options.
     * @throws InvalidOptionException Thrown when encountering an invalid or unaccepted option.
     */
    public static function init($options)
    {
        $accepted_options = array(
            'driver'   => array(
                'type' => 'string',
            ),
            'host'     => array(
                'type' => 'string',
            ),
            'port'     => array(
                'type' => 'number',
            ),
            'dbname'   => array(
                'type' => 'string',
            ),
            'charset'  => array(
                'type' => 'string',
            ),
            'user'     => array(
                'type' => 'string',
            ),
            'password' => array(
                'type' => 'string',
            ),
        );

        foreach ($options as $option_key => $option_value) {
            if (!isset($accepted_options[ $option_key ])) {
                throw new InvalidOptionException('Invalid option provided.');
            }

            $accepted_option = $accepted_options[ $option_key ];

            self::$$option_key = $option_value;
        }
    }

    /**
     * Returns the current instance;
     */
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new Database(
                self::$driver,
                self::$host,
                self::$port,
                self::$dbname,
                self::$charset,
                self::$user,
                self::$password
            );
        }

        return self::$instance;
    }

    /**
     * Constructor
     *
     * @param string $driver The driver.
     * @param string $host The host.
     * @param int    $port The port.
     * @param string $dbname The database name.
     * @param string $charset The charset.
     * @param string $user The user to connect with.
     * @param string $password The password to connect with.
     */
    private function __construct($driver, $host, $port, $dbname, $charset, $user, $password)
    {
        $this->connection = new \PDO("$driver:host=$host:$port;dbname=$dbname;charset=$charset", $user, $password);
    }

    /**
     * Prepares an SQL query for use
     *
     * @param string $query The query to prepare.
     * @param Array  $driver_options A list of driver options to pass along.
     */
    public function prepare($query, $driver_options = array())
    {
        return $this->connection->prepare($query, $driver_options);
    }

    /**
     * Returns results as an object array.
     *
     * @param string|\PDOStatement $query The query to execute.
     * @param Array                $substitutions A list of substitutions to pass along to a prepared statement.
     */
    public function getResults($query, $substitutions = null)
    {
        if ($query instanceof \PDOStatement) {
            $statement = $query;
            if (!is_null($substitutions)) {
                $statement->execute($substitutions);
            }
        } else {
            if (! is_null($substitutions)) {
                throw new ArgumentException('Substitutions must be used in conjunction with a PDOStatement. Did you forget a call to Database::prepare()?', 1000, \E_USER_WARNING);
            }

            $statement = $this->connection->query($query);
        }

        if (false === $statement) {
            $this->handleQueryError();
        }

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Returns a single result
     *
     * @param string|\PDOStatement $query The query to execute.
     * @param Array                $substitutions A list of substitutions to pass along to a prepared statement.
     * @param int                  $db_type The database type to convert result to.
     * @throws InvalidConversionException Exception thrown when failing to convert result into provided $db_type.
     * @throws ArgumentException Exception thrown when receiving unexpected arguments.
     */
    public function getVar($query, $substitutions = null, $db_type = self::DBTYPE_STRING)
    {
        if ($query instanceof \PDOStatement) {
            $statement = $query;
            if (! is_null($substitutions)) {
                $statement->execute($substitutions);
            }
        } else {
            if (! is_null($substitutions)) {
                throw new ArgumentException(
                    'Substitutions must be used in conjunction with a PDOStatement. Did you forget a call to Database::prepare()?',
                    1000
                );
            }

            $statement = $this->connection->query($query);
        }

        if (false === $statement) {
            $this->handleQueryError($statement);
        }

        $result = $statement->fetchAll(\PDO::FETCH_COLUMN);

        if (count($result) > 0) {
            $result = $result[0];
            switch ($db_type) {
                case self::DBTYPE_STRING:
                    return $result;
                case self::DBTYPE_BOOL:
                    $converted_result = filter_var($result, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if (is_null($converted_result)) {
                        throw new InvalidConversionException(
                            sprintf('Cannot convert %s to boolean', $result),
                            self::DBTYPE_BOOL
                        );
                    }

                    return $converted_result;
                case self::DBTYPE_NUMBER:
                    return intval($result);
                case self::DBTYPE_FLOAT:
                    return floatval($result);
                case self::DBTYPE_SERIALIZED:
                    $unserialized_result = @unserialize($result);
                    if (false === $unserialized_result) {
                        throw new InvalidConversionException(
                            sprintf('Cannot unserialize %s', $result),
                            self::DBTYPE_SERIALIZED
                        );
                    }

                    return $unserialized_result;
                case self::DBTYPE_JSON:
                    $decoded_result = json_decode($result);
                    if (is_null($decoded_result)) {
                        throw new InvalidConversionException(
                            sprintf('Cannot decode %s', $result),
                            self::DBTYPE_JSON
                        );
                    }

                    return json_decode($decoded_result);
                default:
                    return $result;
            }
        }

        return null;
    }

    /**
     * Inserts data and returns the IDs inserted.
     *
     * @param string  $query The query to execute.
     * @param Array   $substitutions A list of substitutions. If $multiple is TRUE $substitutions needs to be a two-dimensional array with substitutions.
     * @param boolean $multiple If there's several inserts being made.
     * @throws \Exception The exception thrown when failing to insert multiple rows.
     */
    public function insert($query, $substitutions = null, $multiple = false)
    {
        if ($multiple && ! is_null($substitutions)) {
            $statement  = $this->prepare($query);
            $insert_ids = array();
            try {
                $this->connection->beginTransaction();
                foreach ($substitutions as $substitution) {
                    if (! $statement->execute($substitution)) {
                        $this->handleQueryError();
                    }

                    $insert_ids[] = $this->connection->lastInsertId();
                }

                $this->connection->commit();
            } catch (\Exception $ex) {
                $this->connection->rollback();
                throw $ex;
            }

            return $insert_ids;
        }

        if (! is_null($substitutions)) {
            $statement = $this->prepare($query);
            if (! $statement->execute($substitutions)) {
                $this->handleQueryError();
            }

            return $this->connection->lastInsertId();
        }

        if (! $this->connection->exec($query)) {
            $this->handleQueryError();
        }

        return $this->connection->lastInsertId();
    }

    /**
     * Handles query error throwing.
     *
     * @throws \Exception Generated exception from PDO information.
     */
    private function handleQueryError()
    {
        $error_info = $this->connection->errorInfo();

        $error_message = 'Unexpected database error.';
        if (isset($error_info[2])) {
            $error_message = $error_info[2];
        }

        $error_code = -1;
        if (isset($error_info[1])) {
            $error_code = $error_info[1];
        }

        throw new \Exception($error_message, $error_code);
    }
}
