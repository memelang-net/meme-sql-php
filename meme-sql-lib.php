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

	// Detect and fix any extra spaces around operators, then log a silent warning
	$originalQuery = $query;
	$query = preg_replace('/\s([<>=#]=|[<>])\s/', '$1', $query);
	if ($query !== $originalQuery) {
		// Silent warning that extra spaces around operators were detected and removed
		trigger_error("Warning: Extra spaces around operators were removed in query.", E_USER_NOTICE);
	}

	$clauses = explode('|', $query);  // Split into AND, AND-NOT, and FILTER clauses

	// Ensure we have three clauses for AND, AND-NOT, and FILTER even if empty
	$andClause = isset($clauses[0]) ? $clauses[0] : '';
	$andNotClause = isset($clauses[1]) ? $clauses[1] : '';
	$filterClause = isset($clauses[2]) ? trim($clauses[2]) : '';  // Trim the filter clause

	// Output a simpler SQL query if only the AND clause has conditions
	if ($andClause && empty($andNotClause) && $filterClause === '') {
		$expressions = explode(' ', trim($andClause));
		$conditions = [];
		foreach ($expressions as $expr) {
			$result = memeParse($expr);
			$conditions[] = $result['clause'];
		}
		return "SELECT * FROM " . DB_TABLE . " WHERE " . implode(" AND ", $conditions);
	}

	return "SELECT m.* FROM " . DB_TABLE . " m " . 
	       memeJunction($andClause, $andNotClause, $filterClause);
}

// Handle AND, AND-NOT, and EXTRA FILTER conditions based on clause type
function memeJunction($andClause, $andNotClause, $filterClause) {
	$filters = [];
	$havingConditions = [];
	$notConditions = [];

	// Process AND conditions (clause 0)
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

	// Process AND-NOT conditions (clause 1)
	if ($andNotClause) {
		$expressions = explode(' ', trim($andNotClause));
		foreach ($expressions as
