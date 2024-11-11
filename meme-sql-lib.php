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

// Translate the memelang query into SQL
function memeSQL($query) {
	$query = preg_replace('/\s+/', ' ', trim($query));  // Normalize whitespace

	if ($query=='GET ALL') return "SELECT * FROM " . DB_TABLE;


	// Check for erroneous spaces around operators and fix silently
	$originalQuery = $query;
	$query = preg_replace('/\s([<>=#]=|[<>])\s/', '$1', $query);
	if ($query !== $originalQuery) {
		trigger_error("Warning: Extra spaces around operators were removed in query.", E_USER_NOTICE);
	}

	// Identify positions of NOT and GET
	$notPos = strpos($query, 'NOT');
	$getPos = strpos($query, 'GET');

	// Error handling: if NOT appears after GET, throw an error
	if ($notPos !== false && $getPos !== false && $notPos > $getPos) {
		throw new Exception("Error: 'NOT' must be before 'GET' in the query.");
	}

	// Initialize clause variables
	$andClause = '';
	$andNotClause = '';
	$filterClause = '';

	// Parse based on the presence and order of NOT and GET
	if ($notPos === false && $getPos === false) {
		// Only AND clause is present
		$andClause = $query;
	} elseif ($notPos !== false && $getPos === false) {
		// AND and NOT clauses are present
		$andClause = trim(substr($query, 0, $notPos));
		$andNotClause = trim(substr($query, $notPos + 3));
	} elseif ($notPos === false && $getPos !== false) {
		// AND and GET clauses are present
		$andClause = trim(substr($query, 0, $getPos));
		$filterClause = trim(substr($query, $getPos + 3));
	} else {
		// AND, NOT, and GET clauses are present
		$andClause = trim(substr($query, 0, $notPos));
		$andNotClause = trim(substr($query, $notPos + 3, $getPos - $notPos - 3));
		$filterClause = trim(substr($query, $getPos + 3));
	}

	// Output a simpler SQL query if only the AND clause has conditions and no GET or NOT is present
	if ($getPos === false && $notPos === false) {
		$expressions = explode(' ', trim($andClause));
		$conditions = [];
		foreach ($expressions as $expr) {
			$result = memeParse($expr);
			$conditions[] = $result['clause'];
		}
		return "SELECT * FROM " . DB_TABLE . " WHERE " . implode(" AND ", $conditions);
	}

	// Generate complex SQL query with HAVING
	return "SELECT m.* FROM " . DB_TABLE . " m " . 
	       memeJunction($andClause, $andNotClause, $filterClause);
}

// Handle AND, AND-NOT, and EXTRA FILTER conditions based on clause type
function memeJunction($andClause, $andNotClause, $filterClause) {
	$filters = [];
	$havingConditions = [];
	$notConditions = [];

	// Process AND conditions (true matches)
	if ($andClause) {
		$expressions = explode(' ', trim($andClause));
		foreach ($expressions as $expr) {
			$result = memeParse($expr);
			$havingConditions[] = "SUM(CASE WHEN " . $result['clause'] . " THEN 1 ELSE 0 END) > 0";
			if ($result['filter']) {
				$filters[] = "(" . $result['filter'] . ")";
			}
		}
	}

	// Process AND-NOT conditions (false matches)
	if ($andNotClause) {
		$expressions = explode(' ', trim($andNotClause));
		foreach ($expressions as $expr) {
			$result = memeParse($expr);
			$notConditions[] = "SUM(CASE WHEN " . $result['clause'] . " THEN 1 ELSE 0 END) = 0";
		}
	}

	// Clear filters if the filterClause is "ALL"
	if ($filterClause === 'ALL') $filters = [];

	// Process EXTRA FILTER conditions (GET clause)
	elseif ($filterClause !== '') {
		$expressions = explode(' ', trim($filterClause));
		foreach ($expressions as $expr) {
			$filterResult = memeParse($expr);
			if ($filterResult['filter']) {
				$filters[] = "(" . $filterResult['filter'] . ")";
			}
		}
	}

	$whereClause = !empty($filters) ? " WHERE " . implode(" OR ", memeFilterGroup($filters)) : "";
	$havingClause = implode(" AND ", array_merge($havingConditions, $notConditions));
	return "JOIN (SELECT aid FROM " . DB_TABLE . " GROUP BY aid HAVING " . $havingClause . ") AS aids ON m.aid = aids.aid" . $whereClause;
}

// Parse individual components of a memelang query
function memeParse($query) {
	$pattern = '/^([A-Za-z0-9]*)\.?([A-Za-z0-9]*):?([A-Za-z0-9]*)?([<>=#]*)?(-?\d*\.?\d*)$/';
	$matches = [];
	if (preg_match($pattern, $query, $matches)) {
		$aid = $matches[1] ?: null;
		$rid = $matches[2] ?: null;
		$bid = $matches[3] ?: null;
		$operator = str_replace('#=', '=', $matches[4] ?: '!=');
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

?>
