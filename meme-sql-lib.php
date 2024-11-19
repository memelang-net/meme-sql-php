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

// Translate a Memelang query (which may contain multiple commands) into SQL
function memeSQL($memelangQuery) {
	$queries=[];
	$commands=memeDecode($memelangQuery);
	foreach ($commands as $command) $queries[]=memeCmdSQL($command);
	return implode(' UNION ', $queries);
}

// Translate one Memelang command into SQL
function memeCmdSQL($command) {
	$trueGroup = [];
	$falseGroup = [];
	$filterGroup = [];
	$orGroups = []; // To hold statements grouped by t$n
	$querySettings = ['all' => false];

	foreach ($command as $statement) {
		if ($statement[0][0] === MEME_A && $statement[0][1] === 'qry') {
			$querySettings[$statement[1][1]] = true;
			continue;
		}

		$lastexp = !empty($statement) ? end($statement) : null;

		if (!$lastexp) continue;

		if ($lastexp[0] === MEME_EQ) {
			if ($lastexp[1] === MEME_FALSE) {
				$falseGroup[] = array_slice($statement, 0, -1);
				continue;
			}
			if ($lastexp[1] === MEME_GET) {
				$filterGroup[] = array_slice($statement, 0, -1);
				continue;
			}
		}

		// Group =tn into OR groups
		if ($lastexp[0] === MEME_ORG) {
			$orGroups[$lastexp[1]][] = array_slice($statement,0,-1);
			$filterGroup[] = array_slice($statement, 0, -1);
			continue;
		}

		// Default: Add to true conditions
		$trueGroup[] = $statement;
		$filterGroup[] = $statement;
	}

	// Get all
	if ($querySettings['all'] && empty($trueGroup) && empty($falseGroup) && empty($orGroups)) {
		return "SELECT * FROM " . DB_TABLE;
	}

	// Simple query
	if (count($trueGroup) == 1 && empty($falseGroup) && empty($orGroups) && count($filterGroup) == 1 && !$querySettings['all']) {
		return "SELECT * FROM " . DB_TABLE . " WHERE " . memeWhere($trueGroup[0]);
	}

	// Clear filters if qry.all is present
	if ($querySettings['all']) $filterGroup = [];

	// Generate SQL query for complex cases
	$havingSQL = [];

	// Process AND conditions
	foreach ($trueGroup as $statement) {
		$havingSQL[] = "SUM(CASE WHEN (" . memeWhere($statement) . ") THEN 1 ELSE 0 END) > 0";
	}

	// Process grouped OR conditions
	foreach ($orGroups as $orGroup) {
		$orSQL = [];
		foreach ($orGroup as $orState) $orSQL[] = '('.memeWhere($orState).')';
		$havingSQL[] = "SUM(CASE WHEN (" . implode(" OR ", $orSQL) . ") THEN 1 ELSE 0 END) > 0";
	}

	// Process NOT conditions
	foreach ($falseGroup as $statement) {
		$havingSQL[] = "SUM(CASE WHEN (" . memeWhere($statement) . ") THEN 1 ELSE 0 END) = 0";
	}

	// Process GET filters (only if ALL is not specified)
	$filterSQL = [];
	$whereSQL = '';
	if (!empty($filterGroup)) {
		foreach ($filterGroup as $statement) {
			$filterSQL[] = "(" . memeWhere($statement, false) . ")";
		}
		$whereSQL = " WHERE " . implode(" OR ", memeFilterGroup($filterSQL));
	}

	return "SELECT m.* FROM " . DB_TABLE . " m JOIN (SELECT aid FROM " . DB_TABLE . " GROUP BY aid HAVING " . implode(" AND ", $havingSQL) . ") AS aids ON m.aid = aids.aid" . $whereSQL;
}


// Translate an $statement array of ARBQ into an SQL WHERE clause
function memeWhere($statement, $useQnt = true) {
	global $rOPR;

	$conditions = [];
	$rids = [];
	$aid = null;
	$bid = null;
	$opr = null;
	$qnt = null;
	$ridNest = '';

	if ($useQnt) {
		$opr = '!=';
		$qnt = 0;
	}

	foreach ($statement as $exp) {
		if ($exp[0] === MEME_A) $aid = $exp[1];
		elseif ($exp[0] === MEME_R) $rids[] = $exp[1];
		elseif ($exp[0] === MEME_B) $bid = $exp[1];
		elseif ($useQnt && isset($rOPR[$exp[0]])) {
			$opr = $exp[0]===MEME_DEQ ? '=' : $rOPR[$exp[0]];
			$qnt = $exp[1];
		}
	}


	$ridCount=count($rids);
	if ($ridCount>1) {
		for ($i=1;$i<$ridCount; $i++) {
			if ($i>1) $ridNest.=' AND ';
			$ridNest.="bid in (SELECT aid FROM " . DB_TABLE . " WHERE rid='{$rids[$i]}'";
			if ($i===$ridCount-1) {
				if ($bid) $ridNest.= "AND bid='$bid'";
				if ($useQnt) $ridNest.= " AND qnt$opr$qnt";
			}
		}

		$ridNest .= str_repeat(')', $ridCount - 1);
		$bid=null;
		$useQnt=null;
	}

	if ($aid) $conditions[] = "aid='$aid'";
	if (!empty($rids)) $conditions[] = "rid='{$rids[0]}'";
	if ($bid) $conditions[] = "bid='$bid'";
	if ($useQnt) $conditions[] = "qnt$opr$qnt";
	if ($ridNest) $conditions[] = $ridNest;

	return implode(' AND ', $conditions);
}







// Group filters to reduce SQL complexity, applied only in the WHERE clause
function memeFilterGroup($filters) {
	$ridValues = [];
	$bidValues = [];
	$mixedValues = [];

	foreach ($filters as $filter) {
		if (preg_match("/^\\(rid='([A-Za-z0-9\_]+)'\\)$/", $filter, $matches)) {
			$ridValues[] = $matches[1];
		} elseif (preg_match("/^\\(bid='([A-Za-z0-9\_]+)'\\)$/", $filter, $matches)) {
			$bidValues[] = $matches[1];
		} else {
			$mixedValues[] = $filter;
		}
	}

	$grouped = [];
	if (!empty($ridValues)) {
		$grouped[] = "m.rid IN ('" . implode("','", array_unique($ridValues)) . "')";
	}
	if (!empty($bidValues)) {
		$grouped[] = "m.bid IN ('" . implode("','", array_unique($bidValues)) . "')";
	}

	return array_merge($grouped, $mixedValues);
}

// Tokenize DB output
function memeDbDecode ($triples) {
	$commands=[];
	foreach ($triples as $row) {

		if ($row['qnt']==1) {
			$opr=MEME_EQ;
			$qnt=MEME_TRUE;
		}
		else if ($row['qnt']==0) {
			$opr=MEME_EQ;
			$qnt=MEME_FALSE;
		}
		else {
			$opr=MEME_DEQ;
			$qnt=(float)$row['qnt'];
		}

		$commands[]=[[
			[MEME_A, $row['aid']],
			[MEME_R, $row['rid']],
			[MEME_B, $row['bid']],
			[$opr, $qnt]
		]];
	}
	return $commands;
}

// Output DB data
function memeDBOut ($triples, $set=[]) {
	print memeEncode(memeDbDecode($triples), $set);
}

?>