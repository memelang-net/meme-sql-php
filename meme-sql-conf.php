<?php

// Database configuration constants
define('DB_TYPE', 'sqlite3');      	// Options: 'sqlite3', 'mysql', 'postgres'
define('DB_PATH', 'data.sqlite');   // Default path for SQLite3
define('DB_HOST', 'localhost');    	// Host for MySQL/Postgres
define('DB_USER', 'username');     	// Username for MySQL/Postgres
define('DB_PASSWORD', 'password'); 	// Password for MySQL/Postgres
define('DB_NAME', 'database_name'); // Database name for MySQL/Postgres
define('DB_TABLE_MEME', 'meme');    // Default table name for meme data
define('DB_TABLE_TERM', 'term');    // Default table name for term data

define('COL_AID', 0);
define('COL_RID', 1);
define('COL_BID', 2);
define('COL_QNT', 3);


function memeSQLDB ($sqlQuery) {
	switch (DB_TYPE) {
		case 'sqlite3': return memeSQLite3($sqlQuery);
		case 'mysql': return memeMySQL($sqlQuery);
		case 'postgres': return memePostgres($sqlQuery);
		default: throw new Exception("Unsupported database type: " . DB_TYPE);
	}
}

// SQLite3 database query function
function memeSQLite3($sqlQuery) {
	$db = new SQLite3(DB_PATH);
	$results = [];
	$queryResult = $db->query($sqlQuery);

	while ($row = $queryResult->fetchArray(SQLITE3_NUM)) {
		if (strpos($row[COL_RID], "\t")===false) $results[] = serialize($row);
		else {
			$aid=$row[COL_AID];
			$rids=explode("\t", $row[COL_RID]);
			$bids=explode("\t", $row[COL_BID]);
			$qnts=explode("\t", $row[COL_QNT]);
			foreach ($rids as $j=>$rid) {
				$results[]=serialize([COL_AID=>$aid,COL_RID=>$rid,COL_BID=>$bids[$j],COL_QNT=>$qnts[$j]]);
				$aid=$bids[$j];
			}
		}
	}

	$db->close();
	return array_map('unserialize', array_unique($results));
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
		while ($row = $queryResult->fetch_array()) {
			if (strpos($row[COL_RID], "\t")===false) $results[] = serialize($row);
			else {
				$aid=$row[COL_AID];
				$rids=explode("\t", $row[COL_RID]);
				$bids=explode("\t", $row[COL_BID]);
				$qnts=explode("\t", $row[COL_QNT]);
				foreach ($rids as $j=>$rid)
					$results[]=serialize([COL_AID=>$aid,COL_RID=>$rid,COL_BID=>$bids[$j],COL_QNT=>$qnts[$j]]);
			}
		}
	} else {
		throw new Exception("Query failed: " . $connection->error);
	}

	$connection->close();
	return array_map('unserialize', array_unique($results));
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
		while ($row = pg_fetch_array($queryResult)) {
			if (strpos($row[COL_RID], "\t")===false) $results[] = serialize($row);
			else {
				$aid=$row[COL_AID];
				$rids=explode("\t", $row[COL_RID]);
				$bids=explode("\t", $row[COL_BID]);
				$qnts=explode("\t", $row[COL_QNT]);
				foreach ($rids as $j=>$rid)
					$results[]=serialize([COL_AID=>$aid,COL_RID=>$rid,COL_BID=>$bids[$j],COL_QNT=>$qnts[$j]]);
			}
		}
	} else {
		throw new Exception("Query failed: " . pg_last_error($connection));
	}

	pg_close($connection);
	return array_map('unserialize', array_unique($results));
}


?>
