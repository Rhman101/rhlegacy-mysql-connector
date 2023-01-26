<?php
/**
 * The database connection file. This class will simply open a database connection
 *
 * @author Ruan Huysen <rhuysen@gmail.com>
 * @copyright Copyright (c) 2022, Ruan Huysen
 * 
 * @version 0.0.1
 */

namespace MySQL 
{

	class Connection 
	{
		/**
		 * Static function that returns the connection that was initialised by the constructor.
		 *
		 * @return Connection The database connection.
		 */
		public static function connect(string $DB_HOST, 
									   string $DB_USER_ACCOUNT, 
									   string $DB_USER_PASSWORD, 
									   string $DB_INSTANCE): \PDO 
		{
			$Database = new \PDO("mysql:host=$DB_HOST;dbname=$DB_INSTANCE", $DB_USER_ACCOUNT, $DB_USER_PASSWORD);
			$Database->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
			$Database->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
            $Database->setAttribute(\PDO::ATTR_PERSISTENT, false);
			return $Database;
		}
	}
}
