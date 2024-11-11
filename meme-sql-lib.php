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
	$query = preg_replace('/\s+/', '', $query);

	// Check if it is a simple query without AND, AND-NOT, or OR operators
	if (strpos($query, '&') === false && strpos($query, '|') === false) {
		$result = memeParse($query);
		return "SELECT * FROM " . DB_TABLE . " WHERE " . $result['clause'];
	}

	return "SELECT m.* FROM " . DB_TABLE . " m " . memeJunction($query);
}

// Handle AND, AND-NOT (&!), and OR conditions
function memeJunction($query) {
	$filters = [];
	$havingConditions = [];
	$notConditions = [];

	// Split the query based on operators, retaining delimiters
	if (strpos($query, '&') !== false || strpos($query, '|') !== false) {
		$clauses = preg_split('/(&!|&|\|)/', $query, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		for ($i = 0; $i < count($clauses); $i += 2) {
			$clause = trim($clauses[$i]);
			$operator = ($i > 0) ? $clauses[$i - 1] : '&';

			// Handle OR conditions with "|", add only as a filter
			if ($operator === '|') {
				$filterResult = memeParse($clause);
				if ($filterResult['filter']) {
					$filters[] = "(" . $filterResult['filter'] . ")";
				}

			// Handle AND-NOT conditions with "&!", add as NOT condition in HAVING
			} elseif ($operator === '&!') {
				$result = memeParse($clause);
				$notConditions[] = "SUM(CASE WHEN " . $result['clause'] . " THEN 1 ELSE 0 END) = 0";

			// Handle regular AND conditions with "&"
			} else {
				$result = memeParse($clause);
				$havingConditions[] = "SUM(CASE WHEN " . $result['clause'] . " THEN 1 ELSE 0 END) > 0";
				if ($result['filter']) {
					$filters[] = "(" . $result['filter'] . ")";
				}
			}
		}
		$whereClause = !empty($filters) ? " WHERE " . implode(" OR ", memeFilterGroup($filters)) : "";
		$havingClause = implode(" AND ", array_merge($havingConditions, $notConditions));
		return "JOIN (SELECT aid FROM " . DB_TABLE . " GROUP BY aid HAVING " . $havingClause . ") AS aids ON m.aid = aids.aid" . $whereClause;
	}

	// Handle as a single WHERE clause if no AND, OR, or AND-NOT
	$result = memeParse($query);
	if ($result['filter']) {
		$filters[] = "(" . $result['filter'] . ")";
	}
	return "WHERE " . $result['clause'] .
		(!empty($filters) ? " AND " . implode(" OR ", memeFilterGroup($filters)) : "");
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
