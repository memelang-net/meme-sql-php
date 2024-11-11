<?php

// Database configuration constants
define('DB_TYPE', 'sqlite3');      	// Options: 'sqlite3', 'mysql', 'postgres'
define('DB_PATH', 'data.sqlite');   // Default path for SQLite3
define('DB_HOST', 'localhost');    	// Host for MySQL/Postgres
define('DB_USER', 'username');     	// Username for MySQL/Postgres
define('DB_PASSWORD', 'password'); 	// Password for MySQL/Postgres
define('DB_NAME', 'database_name'); // Database name for MySQL/Postgres
define('DB_TABLE', 'meme');        	// Default table name for queries



// SQLite3 database query function
function memeSQLite3($sqlQuery) {
	$db = new SQLite3(DB_PATH);
	$results = [];
	$queryResult = $db->query($sqlQuery);

	while ($row = $queryResult->fetchArray(SQLITE3_ASSOC)) {
		$results[] = $row;
	}

	$db->close();
	return $results;
}

// MySQL database query function
function memeMySQL($sqlQuery) {
	$connection = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
	if ($connection->connect_error) {
		throw new Exception("Connection failed: " . $connection->connect_error);
	}

	$results = [];
	$queryResult = $connection->query($sqlQuery);
	if ($queryResult) {
		while ($row = $queryResult->fetch_assoc()) {
			$results[] = $row;
		}
	} else {
		throw new Exception("Query failed: " . $connection->error);
	}

	$connection->close();
	return $results;
}

// PostgreSQL database query function
function memePostgres($sqlQuery) {
	$connectionString = "host=" . DB_HOST . " dbname=" . DB_NAME . " user=" . DB_USER . " password=" . DB_PASSWORD;
	$connection = pg_connect($connectionString);
	if (!$connection) {
		throw new Exception("Connection failed: " . pg_last_error());
	}

	$results = [];
	$queryResult = pg_query($connection, $sqlQuery);
	if ($queryResult) {
		while ($row = pg_fetch_assoc($queryResult)) {
			$results[] = $row;
		}
	} else {
		throw new Exception("Query failed: " . pg_last_error($connection));
	}

	pg_close($connection);
	return $results;
}


?>
