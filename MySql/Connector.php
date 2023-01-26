<?php
/**
 * The database connection file. This class will simply open a database connection
 *
 * @author Ruan Huysen <rhuysen@gmail.com>
 * @copyright Copyright (c) 2022, Ruan Huysen
 * 
 * @version 0.0.1
 */

namespace MySql;

use MySql\Connection;
	
class Connector 
{
    /**
     * The connection object
     * @var PDO
     */
    private ?\PDO $connection;

    /**
     * Last used parameters for the connection when constructor was called.
     *
     * @var array
     */
    private array $connectionParams;

    /**
     * The SQL string to execute
     * @var string
     */
    private string $strSql;

    /**
     * The SQL parameters to bind
     */
    private ?array $arrSql;

    /**
     *	Constructor that opens a new database connection
    */
    function __construct(array $connection_params) 
    {
        $this->connectionParams = $connection_params;
       $this->initConnection();
    }

    private function initConnection() {
        $this->connection = Connection::connect(
            $this->connectionParams['host'], 
            $this->connectionParams['user'], 
            $this->connectionParams['passwd'], 
            $this->connectionParams['schema']
        );
    }

    /**
     * Closes the connection to the database
     *
     * @return void
     */
    public function destroy(): void
    {
        $this->connection = null;
    }

    /**
     * Sets the SQL string and parameters to be bound and returns the connector instance
     *
     * @param string $strSql The SQL string to execute
     * @param array|null $arrSql The parameters to be bound with the SQL string
     * @return Connector
     */
    public function prepare(string $strSql, ?array $arrSql = null): Connector
    {
        if ($this->connection == null) {
            $this->initConnection();
        }
        $this->strSql = $strSql;
        $this->arrSql = $arrSql;
        return $this;
    }

    /**
     * Returns the number of rows for the specified query. 
     * If doing a SELECT *, the rowCount will do a COUNT(*) query,
     * otherwise will default to PDO's rowCount();
     * 
     * @return integer
     */
    public function rowCount(): int 
    {
        if (strpos($this->strSql, " * ") !== False) {
            $this->strSql = \str_replace("*", "COUNT(*)", $this->strSql);
            $stmt = $this->connection->prepare($this->strSql);
            $stmt->execute($this->arrSql);
            $result = $stmt->fetchColumn();
            $this->strSql = \str_replace("COUNT(*)", "*", $this->strSql);
            return $result;
        } else {
            $stmt = $this->connection->prepare($this->strSql);
            $stmt->execute($this->arrSql);
            return $stmt->rowCount();
        }
    }

    /**
     * Modifies a table by either inserting, updating or deleting a record. 
     * Requires the connector to be prepared first by using prepare()
     *
     * @return boolean
     */
    public function modify(): bool 
    {
        $result = $this->connection->prepare($this->strSql)
                       ->execute($this->arrSql);

        $this->destroy();
        return $result;
    }

    /**
     * Returns a single column value from a table. 
     * Requires the connector to be prepared first using prepare()
     *
     * @return string|null
     */
    public function query(): ?string 
    {
        $stmt = $this->connection->prepare($this->strSql);
        $stmt->execute($this->arrSql);
        if ($stmt->rowCount() > 0) {
            $result = $stmt->fetchColumn();
            $this->destroy();
            return $result;
        } else {
            $this->destroy();
            return null;
        }
    }

    /**
     * Returns the last inserted record's id.
     *
     * @return integer
     */
    public function lastInsertId(): int
    {
        return $this->prepare("SELECT LAST_INSERT_ID()", null)->query();
    }

    /**
     * Selects records from a table.
     * Requires the connector to be prepared using prepare()
     *
     * @param boolean $multi If true, then a multi-dimensional array will be returned
     * @return array
     */
    public function select($multi = false): array 
    {
        $stmt = $this->connection->prepare($this->strSql);
        $stmt->execute($this->arrSql);

        $rows = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $rows[] = $row;
        }

        $this->destroy();
        
        if (!$multi) {
            return count($rows) > 1 ? $rows : (count($rows) > 0 ? $rows[0] : []);
        } else {
            return $rows;
        }
    }

    /**
     * Quickly delete a record from a table
     *
     * @param string $table The table to delete the record from
     * @param integer $id The primary key id of the record to delete
     * @return boolean
     */
    public function delete(string $table, int $id): bool
    {
        if ($this->prepare("SHOW TABLES LIKE '$table'", null)->rowCount() > 0) {
            $primary_key = $this->prepare("SHOW KEYS FROM $table WHERE Key_name = 'PRIMARY'", null)
                                ->select()['Column_name'];
            return $this->prepare("DELETE FROM $table WHERE $primary_key = :id", [":id" => $id])->modify();
        } else throw new \Exception("Connector Fatal Error: Table '$table' does not exist in this database.");
    }

    /**
     * Quickly insert a record to any table by specifying key value pairs
     *
     * @param string $table The table to insert the record into
     * @param array $key_value_pairs The column values to be inserted as a key value pair array
     * @return boolean
     */
    public function quick_insert(string $table, array $key_value_pairs): bool 
    {
        if ($this->prepare("SHOW TABLES LIKE '$table'", null)->rowCount() > 0) {
            $strSql = "INSERT INTO $table (";
            $arrSql = [];
            foreach ($key_value_pairs as $column => $value) {
                $strSql .= "$column, ";
            }
            $strSql = \rtrim($strSql, ', ').") VALUES (";
            foreach ($key_value_pairs as $column => $value) {
                $strSql .= ":$column, ";
                $arrSql[":$column"] = $value;
            }
            $strSql = \rtrim($strSql, ', ').");";

            return $this->prepare($strSql, $arrSql)->modify();
        } else throw new \Exception("Connector Fatal Error: Table '$table' does not exist in this database.");
    }

    /**
     * Quickly update a record in a table by specifying key value pairs
     *
     * @param string $table The table to update
     * @param integer $id The primary key id of the record to update
     * @param array $key_value_pairs The column values to update as a key value pair array
     * @return boolean
     */
    public function quick_update(string $table, int $id, array $key_value_pairs): bool 
    {
        if ($this->prepare("SHOW TABLES LIKE '$table'", null)->rowCount() > 0) {
            $primary_key = $this->prepare("SHOW KEYS FROM $table WHERE Key_name = 'PRIMARY'", null)
                                ->select()['Column_name'];

            $strSql = "UPDATE $table SET ";
            $arrSql = [];
            foreach ($key_value_pairs as $column => $value) {
                $strSql .= "$column = :$column, ";
                $arrSql[":$column"] = $value;
            }
            $strSql = \rtrim($strSql, ', ')." WHERE $primary_key = $id";

            return $this->prepare($strSql, $arrSql)->modify();
        } else throw new \Exception("Connector Fatal Error: Table '$table' does not exist in this database.");
    }

    /**
     * Inserts records in bulk using a multidimentional array as key value pairs
     *
     * @param string $table The table to insert data into
     * @param array $data The data to be inserted as a multidimentional key value pairs array
     * @return boolean
     */
    public function bulk_insert(string $table, array $data): bool 
    {
        if ($this->prepare("SHOW TABLES LIKE '$table'", null)->rowCount() > 0) {
            $i = 0;
            $strSql = "";
            $arrSql = [];
            foreach ($data as $row) {
                if (!empty($strSql)) {
                    $strSql .= ", ( ";
                } else {
                    $ii = 0;
                    foreach ($row as $column => $value) {
                        if ($ii == 0) {
                            $strSql .= "INSERT INTO $table ($column, ";
                        } else {
                            $strSql .= "$column, ";
                        }
                        $ii++;
                    }
                    $strSql = rtrim($strSql, ', '). ") VALUES ( ";
                }

                foreach ($row as $column => $value) {
                    $strSql .= sprintf(":%s_$i, ", $column);
                    $arrSql[sprintf(":%s_$i", $column)] = $value;
                }
                $strSql = rtrim($strSql, ', ').')';
                $i++;
            }
            return $this->prepare($strSql, $arrSql)->modify();
        } else throw new \Exception("Connector Fatal Error: Table '$table' does not exist in this database.");
    }

    /**
     * Execute multiple insert, update or delete statements
     * This function requires the connector to be prepared using prepare()
     * Statements are separated by ";"
     *
     * @return boolean
     */
    public function m_modify(): bool 
    {
        $statements = explode(";", $this->strSql);

        $executed = 0;
        $stmt_count = 0;
        foreach ($statements as $strSql) {
            $stmt = $this->connection->prepare($strSql);
        
            if ($stmt->execute($this->arrSql[$stmt_count])) {
                $executed++;
            }
            $stmt_count++;
        }

        $this->destroy();

        return $executed == $stmt_count;
    }

    /**
     * Initiates a mysql transaction
     * Turns off autocommit mode. While autocommit mode is turned off, changes made to the database via the PDO object instance are not committed
     * until you end the transaction by calling {@link Connector::commit()}.
     *
     * @return Connector
     * @throws PDOException
     * If there is already a transaction started or the driver does not support transactions
     * Note: An exception is raised even when the PDO::ATTR_ERRMODE attribute is not PDO::ERRMODE_EXCEPTION.
     */
    public function beginTransaction(): Connector 
    {
        $this->connection->beginTransaction();
        return $this;
    }

    /**
     * Commits a mysql transaction
     *
     * @return boolean
     * @throws PDOException — if there is no active transaction.
     */
    public function commit(): bool 
    {
        return $this->connection->commit();
    }

    /**
     * Rolls back a mysql transaction
     *
     * @return boolean
     * @throws PDOException — if there is no active transaction.
     */
    public function rollback(): bool
    {
        return $this->connection->rollBack();
    }
}