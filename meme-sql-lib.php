<?php

// Main function to process memelang query and return results
function memeQuery($memelangQuery) {
	try {
		$sqlQuery = memeSQL($memelangQuery);

		switch (DB_TYPE) {
			case 'sqlite3': return memeSQLite3($sqlQuery);
			case 'mysql': return memeMySQL($sqlQuery);
			case 'postgres': return memePostgres($sqlQuery);
			default: throw new Exception("Unsupported database type: " . DB_TYPE);
		}
	} catch (Exception $e) {
		return "Error: " . $e->getMessage();
	}
}

function memeSQL($memelangQuery) {
	$queries=[];
	$commands=memeDecode($memelangQuery);
	foreach ($commands as $command) $queries[]=memeCmdSQL($command);
	return implode(' UNION ', $queries);
}

function memeCmdSQL ($command) {

	$trueConditions = [];
	$falseConditions = [];
	$getFilters = [];
	$querySettings=[
		'all'=>false
	];

	foreach ($command as $statement) {
		if ($statement[0][0]===A && $statement[0][1]==='qry') {
			$querySettings[$statement[1][1]]=true;
			continue;
		}

		$lastexp=end($statement);

		if ($lastexp[0]===EQ) {
			if ($lastexp[1]===0) {
				$falseConditions[]=array_slice($statement, 0, -1);
				continue;
			}
			if ($lastexp[1]==='g') {
				$getFilters[]=array_slice($statement, 0, -1);
				continue;
			}
		}

		$trueConditions[]=$statement;
		$getFilters[]=$statement;
	}

	// Get all
	if ($querySettings['all'] && empty($trueConditions) && empty($falseConditions)) 
		return "SELECT * FROM " . DB_TABLE;

	// Simple query
	if (count($trueConditions)==1 && empty($falseConditions) && count($getFilters)==1 && !$querySettings['all'])
		return "SELECT * FROM " . DB_TABLE . " WHERE " . memeWhere($trueConditions[0]);

	// Clear filters if ALL is present
	if ($querySettings['all']) $getFilters = [];

	// Generate SQL query for complex cases

	$havingConditions = [];

	// Process AND conditions
	foreach ($trueConditions as $statement) {
		$havingConditions[] = "SUM(CASE WHEN (".memeWhere($statement).") THEN 1 ELSE 0 END) > 0";
	}

	// Process NOT conditions
	foreach ($falseConditions as $statement) {
		$havingConditions[] = "SUM(CASE WHEN (".memeWhere($statement).") THEN 1 ELSE 0 END) = 0";
	}

	// Process GET filters (only if ALL is not specified)
	$filters = [];
	$whereClause = '';
	if (!empty($getFilters)) {
		foreach ($getFilters as $statement) $filters[] = "(".memeWhere($statement, false).")";
		$whereClause = " WHERE " . implode(" OR ", memeFilterGroup($filters));
	}

	return "SELECT m.* FROM " . DB_TABLE . " m JOIN (SELECT aid FROM " . DB_TABLE . " GROUP BY aid HAVING ".implode(" AND ", $havingConditions).") AS aids ON m.aid = aids.aid" . $whereClause;
}


function memeWhere ($statement, $qnt=true) {
	global $rOPR;
	$qFound=false;
	$kv=[];
	$count=count($statement);

	foreach ($statement as $exp) {
		if ($exp[0]===A) $kv[]="aid='{$exp[1]}'";
		else if ($exp[0]===R) $kv[]="rid='{$exp[1]}'";
		else if ($exp[0]===B) $kv[]="bid='{$exp[1]}'";
		else if ($qnt && $rOPR[$exp[0]]) {
			$qFound=true;
			
			if ($exp[0]===EQ) $kv[]="qnt!=0";
			else {
				if ($exp[0]===DEQ) $exp[0]=EQ;
				$kv[]='qnt' . $rOPR[$exp[0]] . $exp[1];
			}
		}
	}

	if ($qnt && !$qFound) $kv[]="qnt!=0";

	return implode (' AND ', $kv);
}


// Group filters to reduce SQL complexity, applied only in the WHERE clause
function memeFilterGroup($filters) {
	$ridValues = [];
	$bidValues = [];
	$complexFilters = [];

	foreach ($filters as $filter) {
		if (preg_match("/^\\(rid='([A-Za-z0-9\_]+)'\\)$/", $filter, $matches)) {
			$ridValues[] = $matches[1];
		} elseif (preg_match("/^\\(bid='([A-Za-z0-9\_]+)'\\)$/", $filter, $matches)) {
			$bidValues[] = $matches[1];
		} else {
			$complexFilters[] = $filter;
		}
	}

	$grouped = [];
	if (!empty($ridValues)) {
		$grouped[] = "m.rid IN ('" . implode("','", array_unique($ridValues)) . "')";
	}
	if (!empty($bidValues)) {
		$grouped[] = "m.bid IN ('" . implode("','", array_unique($bidValues)) . "')";
	}

	return array_merge($grouped, $complexFilters);
}

// Format query results as memelang
function memeTupOut ($results) {
	$memelangOutput = [];
	foreach ($results as $row) {
		$memelangOutput[] = "{$row['aid']}.{$row['rid']}:{$row['bid']}={$row['qnt']}";
	}
	return implode(";\n", $memelangOutput);
}
