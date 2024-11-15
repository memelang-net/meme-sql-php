<?php

// Main function to process memelang query and return results
function memeQuery($memelangQuery) {
	try {
		$sqlQuery = memeSQL($memelangQuery);

		switch (DB_TYPE) {
			case 'sqlite3':
				return memeSQLite3($sqlQuery);
			case 'mysql':
				return memeMySQL($sqlQuery);
			case 'postgres':
				return memePostgres($sqlQuery);
			default:
				throw new Exception("Unsupported database type: " . DB_TYPE);
		}
	} catch (Exception $e) {
		return "Error: " . $e->getMessage();
	}
}

function memeSQL($query) {
	$query = preg_replace('/\s+/', ' ', trim($query)); // Normalize whitespace

	// Automatically remove spaces around operators
	$query = preg_replace('/\s*([\!<>=]+)\s*/', '$1', $query);

	// Split the query into individual statements
	$statements = explode(' ', $query);
	$trueConditions = [];
	$falseConditions = [];
	$getFilters = [];
	$querySettings=[
		'all'=>false
	];

	// Process each statement
	foreach ($statements as $statement) {
		if (strpos($statement, 'qry.') === 0) {
			list(,$qryset)=explode('.', $statement);
			$querySettings[$qryset]=true;
		} elseif (strpos($statement, '=f') !== false) {
			// NOT (false) condition
			$falseConditions[] = memeParse(trim(str_replace('=f', '', $statement)))['clause'];
		} elseif (strpos($statement, '=g') !== false) {
			// GET (filter) condition
			$getFilters[] = memeParse(trim(str_replace('=g', '', $statement)))['filter'];
		} else {
			// Default AND (true) condition
			$parsed = memeParse(trim($statement));
			$trueConditions[] = $parsed['clause'];

			// Populate $getFilters with 'filter' from parsed result
			if (!empty($parsed['filter'])) {
				$getFilters[] = $parsed['filter'];
			}
		}
	}

	if ($querySettings['all'] && empty($trueConditions) && empty($falseConditions)) 
		return "SELECT * FROM " . DB_TABLE;

	// Simple query if there is exactly one AND condition, no NOT or GET clauses, and no ALL
	if (count($trueConditions) == 1 && empty($falseConditions) && empty($getFilters) && !$querySettings['all']) {
		return "SELECT * FROM " . DB_TABLE . " WHERE " . implode(" AND ", $trueConditions);
	}

	// Clear filters if ALL is present
	if ($querySettings['all']) $getFilters = [];

	// Generate SQL query for complex cases
	return "SELECT m.* FROM " . DB_TABLE . " m " . 
	       memeJunction($trueConditions, $falseConditions, $getFilters);
}

// Generate the SQL query junction for AND, NOT, and GET
function memeJunction($trueConditions, $falseConditions, $getFilters) {
	$havingConditions = [];
	$filters = [];
	$whereClause = ''; // Initialize to avoid undefined variable warnings

	// Process AND conditions
	foreach ($trueConditions as $condition) {
		$havingConditions[] = "SUM(CASE WHEN $condition THEN 1 ELSE 0 END) > 0";
	}

	// Process NOT conditions
	foreach ($falseConditions as $condition) {
		$havingConditions[] = "SUM(CASE WHEN $condition THEN 1 ELSE 0 END) = 0";
	}

	// Process GET filters (only if ALL is not specified)
	if (!empty($getFilters)) {
		foreach ($getFilters as $filter) {
			if ($filter) {
				$filters[] = "($filter)";
			}
		}
		$whereClause = " WHERE " . implode(" OR ", memeFilterGroup($filters));
	}

	$havingClause = implode(" AND ", $havingConditions);

	return "JOIN (SELECT aid FROM " . DB_TABLE . " GROUP BY aid HAVING $havingClause) AS aids ON m.aid = aids.aid" . $whereClause;
}

// Parse individual components of a memelang query
function memeParse($query) {
	$query = preg_replace('/=t$/', '', $query);
	$pattern = '/^([A-Za-z0-9\_]*)\.?([A-Za-z0-9\_]*):?([A-Za-z0-9\_]*)?([\!<>=]*)?(-?\d*\.?\d*)$/';
	$matches = [];
	if (preg_match($pattern, $query, $matches)) {
		$aid = $matches[1] ?: null;
		$rid = $matches[2] ?: null;
		$bid = $matches[3] ?: null;
		$operator = $matches[4] ?: '!=';
		$qnt = $matches[5] !== '' ? $matches[5] : '0';

		// Set default quantity condition to "qnt!=0" if no operator and quantity are specified
		$conditions = [];
		if ($aid) $conditions[] = "aid='$aid'";
		if ($rid) $conditions[] = "rid='$rid'";
		if ($bid) $conditions[] = "bid='$bid'";
		$conditions[] = ($matches[4] === '' && $matches[5] === '') ? "qnt!=0" : "qnt$operator$qnt";

		$filterConditions = [];
		if ($rid) $filterConditions[] = "rid='$rid'";
		if ($bid) $filterConditions[] = "bid='$bid'";
		return ["clause" => "(" . implode(' AND ', $conditions) . ")", "filter" => implode(' AND ', $filterConditions)];
	} else {
		throw new Exception("Invalid memelang format: $query");
	}
}

// Group filters to reduce SQL complexity, applied only in the WHERE clause
function memeFilterGroup($filters) {
	$ridValues = [];
	$bidValues = [];
	$complexFilters = [];

	foreach ($filters as $filter) {
		if (preg_match("/^\\(rid='([A-Za-z0-9]+)'\\)$/", $filter, $matches)) {
			$ridValues[] = $matches[1];
		} elseif (preg_match("/^\\(bid='([A-Za-z0-9]+)'\\)$/", $filter, $matches)) {
			$bidValues[] = $matches[1];
		} else {
			$complexFilters[] = $filter;
		}
	}

	$grouped = [];
	if (!empty($ridValues)) {
		$grouped[] = "m.rid IN ('" . implode("','", $ridValues) . "')";
	}
	if (!empty($bidValues)) {
		$grouped[] = "m.bid IN ('" . implode("','", $bidValues) . "')";
	}

	return array_merge($grouped, $complexFilters);
}

// Format query results as memelang
function memeOut($results) {
	$memelangOutput = [];
	foreach ($results as $row) {
		$memelangOutput[] = "{$row['aid']}.{$row['rid']}:{$row['bid']}={$row['qnt']}";
	}
	return implode(";\n", $memelangOutput);
}
