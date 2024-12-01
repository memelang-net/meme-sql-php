<?php

define('MEME_AID_AS_AID', 'm0.aid AS aid');
define('MEME_RID_AS_AID', 'm0.rid AS aid');
define('MEME_BID_AS_AID', 'm0.bid AS aid');


function memeDeterm ($memeCommands, $write=false) {
	global $MEME_TERMS;
	
	$lookups=[];
	$missings=[];

	foreach ($memeCommands as $i=>&$memeCommand) {
		foreach ($memeCommand as $j=>&$memeStatement) {
			foreach ($memeStatement as $k=>&$memeExpression) {
				if (ctype_alpha(substr($memeExpression[1], 0, 1))) {

					if ($MEME_TERMS[$memeExpression[1]]) $memeExpression[1]=$MEME_TERMS[$memeExpression[1]];

					else {
						if (!isset($lookups[$memeExpression[1]])) $lookups[$memeExpression[1]]=MEME_A;

						if ($write) {
							if ($memeExpression[0]===MEME_RA) $lookups[$memeExpression[1]]=MEME_RA;
							else if ($memeExpression[0]===MEME_RB) $lookups[$memeExpression[1]]=MEME_RA;

							// work on this
							else if ($memeStatement[$k+1][1]==='is' && $memeStatement[$k+2][1]==='rel') $lookups[$memeExpression[1]]=MEME_RA;
						}
					}
				}
			}
		}
	}

	if (empty($lookups)) return $memeCommands;

//	print_r($lookups);

	$sqlQuery='SELECT * FROM '. DB_TABLE_TERM .' WHERE str IN (\''.implode("','", array_keys($lookups)).'\')';

//	$rows=memeSQLDB($sqlQuery);

	foreach ($rows as $row) $MEME_TERMS[$row['str']]=$row['aid'];

	foreach ($memeCommands as $i=>&$memeCommand) {
		foreach ($memeCommand as $j=>&$memeStatement) {
			foreach ($memeStatement as $k=>&$memeExpression) {
				if (ctype_alpha(substr($memeExpression[1], 0, 1))) {
					if ($MEME_TERMS[$memeExpression[1]]) $memeExpression[1]=$MEME_TERMS[$memeExpression[1]];
					else $missings[$memeExpression[1]]=1;
				}
			}
		}
	}

	if (!empty($missings)) {
		$writtings=[];
		if (!$write) throw new Exception("Unidentified terms: " . implode(', ', array_keys($missings)));
		else {

//			$maxq=memeSQLDB('SELECT MAX(aid) FROM '.DB_TABLE_TERM);
			$maxq=[[110]];
			$max=(int)current(current($maxq));

			foreach ($missings as $word=>$x) {
				$max++;
				if ($lookups[$word]===MEME_RA || $lookups[$word]===MEME_RB) {
					if ($max%2===1) $max++;
				}
				$MEME_TERMS[$word]=$max;
				$writtings[]='('.$max.','.MEME_TERM.',\''.$word.'\')';
			}
		}

		$writeSQL='INSERT INTO '.DB_TABLE_TERM.' (aid,rid,str) VALUES '.implode(',', $writtings);
		print $writeSQL;
//		memeSQLDB($writeSQL);
	}

	unset($lookups,$missings,$writtings);

	return $memeCommands;
}


// Main function to process memelang query and return results
function memeQuery($memeString) {
	try {
		$sqlQuery = memeSQL($memeString);

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
function memeSQL($memeString) {
	$queries=[];
	$memeCommands=memeDecode($memeString);
	foreach ($memeCommands as $memeCommand) $queries[]=memeCmdSQL($memeCommand);
	return implode(' UNION ALL ', $queries);
}

// Translate one Memelang command into SQL
function memeCmdSQL($memeCommand) {
	$table=DB_TABLE_MEME;
	$querySettings = ['all' => false];
	$trueGroup = [];
	$falseGroup = [];
	$getGroup = [];
	$orGroups = [];
	$trueCount = 0;
	$orCount = 0;
	$falseCount = 0;
	$getCount = 0;

	foreach ($memeCommand as $memeStatement) {
		if ($memeStatement[0][0] === MEME_A && $memeStatement[0][1] === 'qry') {
			$querySettings[$memeStatement[1][1]] = true;
			continue;
		}

		$lastexp = !empty($memeStatement) ? end($memeStatement) : null;

		if (!$lastexp) continue;

		if ($lastexp[0] === MEME_EQ) {
			if ($lastexp[1] === MEME_FALSE) {
				$falseCount++;
				$falseGroup[] = array_slice($memeStatement, 0, -1);
				continue;
			}
			if ($lastexp[1] === MEME_GET) {
				$getCount++;
				$getGroup[] = array_slice($memeStatement, 0, -1);
				continue;
			}
		}

		// Group =tn into OR groups
		if ($lastexp[0] === MEME_ORG) {
			$orCount++;
			$orGroups[$lastexp[1]][] = array_slice($memeStatement,0,-1);
			continue;
		}

		// Default: Add to true conditions

		$aid=null;
		$rids=[];
		$bid=null;
		foreach ($memeStatement as $exp) {
			if ($exp[0] === MEME_A) $aid=$exp[1];
			elseif ($exp[0] === MEME_RA) $rids[] = $exp[1];
			elseif ($exp[0] === MEME_B) $bid = $exp[1];
		}

		$trueCount++;
		$trueGroup[$aid][implode("\t", $rids)][$bid][] = $memeStatement;
	}

	// Get all
	if ($querySettings['all'] && $trueCount===0 && $falseCount===0 && $orCount===0)
		return "SELECT * FROM $table";

	// Simple query
	if ($trueCount===1 && count($memeCommand)===1)
		return implode(' WHERE ', memeWhere($memeStatement));


	// Generate SQL query for complex cases
	$cteSQL = [];
	$cteCount=-1;

	// Process AND conditions
	foreach ($trueGroup as $aidGroup) {
		foreach ($aidGroup as $ridGroup) {
			foreach ($ridGroup as $bidGroup) {
				$wheres=[];
				$cteCount++;

				foreach ($bidGroup as $memeStatement) {
					list($select, $where)=memeWhere($memeStatement);
					if (empty($wheres)) $wheres[]=$where;
					else $wheres[]=substr($where, strpos($where, 'qnt')-4, 99);
				}

				if ($cteCount>0) $wheres[]= ((strpos($select, MEME_BID_AS_AID)) ? 'm0.bid' : 'm0.aid').
					' IN (SELECT aid FROM z'.($cteCount-1).')';

				$cteSQL[$cteCount] = "z$cteCount AS ($select WHERE ". implode(' AND ', $wheres).')';
				$cteOut[]=$cteCount;
			}
		}
	}

	// Process grouped OR conditions
	foreach ($orGroups as $orGroup) {
		$cteCount++;
		$orSQL = [];
		foreach ($orGroup as $memeStatement) {
			$orSQL[]=implode(' WHERE ', memeWhere($memeStatement))
				.($cteCount>0 ? ' AND m0.aid IN (SELECT aid FROM z'.($cteCount-1).')' : '');
		}
		$cteSQL[$cteCount] = "z$cteCount AS (".implode(' UNION ALL ', $orSQL).')';
		$cteOut[]=$cteCount;
	}

	// Process NOT conditions
	if ($falseCount) {
		if ($trueCount<1) throw new Exception('A query with a false statements must contain at least one non-OR true statement.');

		$falseSQL=[];
		foreach ($falseGroup as $memeStatement) $falseSQL[]="aid NOT IN (". implode(' WHERE ', memeWhere($memeStatement, true)).')';

		$fsql="SELECT aid FROM z$cteCount WHERE ".implode(' AND ', $falseSQL);
		$cteSQL[++$cteCount] = "z$cteCount AS ($fsql)";
	}

	$selectSQL=[];

	// select all data related to the matching As
	if ($querySettings['all']) {
		$selectSQL[]="SELECT * FROM $table WHERE aid IN (SELECT aid FROM z{$cteCount})";
		$selectSQL[]='SELECT bid AS aid, CONCAT("\'", rid), aid as bid, qnt FROM '."$table WHERE bid IN (SELECT aid FROM z{$cteCount})";
	}

	// otherwise select the matching and the GET fields
	else {
		foreach ($getGroup as $memeStatement)
			$selectSQL[]=implode(' WHERE ', memeWhere($memeStatement))." AND m0.aid IN (SELECT aid FROM z{$cteCount})";
	}

	foreach ($cteOut as $cteNum)
		$selectSQL[]="SELECT * FROM z{$cteNum}" .($cteNum===$cteCount ? '' : " WHERE aid IN (SELECT aid FROM z{$cteCount})");

	return 'WITH '.implode(', ', $cteSQL).' '.implode(' UNION ALL ', $selectSQL);
}


// Translate an $memeStatement array of ARBQ into an SQL WHERE clause
function memeWhere($memeStatement, $aidOnly=false) {
	global $rOPR;

	$wheres = [];
	$rids = [];
	$aid = null;
	$bid = null;
	$opr = '!=';
	$qnt = 0;
	$selects=[$aidOnly ? 'aid' : '*'];
	$joins=[];

	foreach ($memeStatement as $exp) {
		if ($exp[0] === MEME_A) $aid = $exp[1];
		elseif ($exp[0] === MEME_RA || $exp[0] === MEME_RB || $exp[0] === MEME_RR) $rids[] = $exp;
		elseif ($exp[0] === MEME_B) $bid = $exp[1];
		elseif (isset($rOPR[$exp[0]])) {
			$opr = $exp[0]===MEME_DEQ ? '=' : $rOPR[$exp[0]];
			$qnt = $exp[1];
		}
	}

	$ridCount=count($rids);
	$ridLast=$ridCount?$ridCount-1:0;
	$joinPrev='m0.aid';

	if ($aid) $wheres[] = $rids[0][0] === MEME_RB ? "m0.bid='$aid'" : "m0.aid='$aid'";

	if ($ridCount) {
		$ridList=[];
		$bidList=[];
		$qntList=[];
		for ($i=0;$i<$ridCount; $i++) {

			if ($i<$ridCount-1) $wheres[]="m$i.qnt!=0";

			// link on B
			if ($rids[$i][0] === MEME_RB) {
				if ($i>0) $joins[]="JOIN meme m$i ON $joinPrev=m$i.bid";
				
				$joinPrev = "m$i.aid";
				$wheres[]="m$i.rid=\"{$rids[$i][1]}\"";
				$ridList[]="\"'{$rids[$i][1]}\"";
				$bidList[]="m$i.aid";
				$qntList[]="(CASE WHEN m$i.qnt = 0 THEN 0 ELSE 1 / m$i.qnt END)";

			}

			// link on R->A
			else if ($rids[$i][0] === MEME_RR) {
				$joins[]="JOIN meme mr$i ON m$i.rid=mr$i.aid";
				$wheres[]="mr$i.rid=\"{$rids[$i][1]}\"";
				$joinPrev = "mr$i.aid";
				$ridList[]="m$i.rid";
				$bidList[]="m$i.bid";
				$qntList[]="m$i.qnt";
			}

			// link on A
			else {
				if ($i>0) $joins[]="JOIN meme m$i ON $joinPrev=m$i.aid";

				$joinPrev = "m$i.bid";
				if ($rids[$i][1] !== NULL) $wheres[]="m$i.rid=\"{$rids[$i][1]}\"";

				$ridList[]="m$i.rid";
				$bidList[]="m$i.bid";
				$qntList[]="m$i.qnt";
			}
		}

		if ($ridCount>1 || $rids[0][0] !== MEME_RA) {
			$selects=[$rids[0][0]===MEME_RB ? MEME_BID_AS_AID : MEME_AID_AS_AID];

			if (!$aidOnly) {
				$selects[]=$ridCount>1 ? 'CONCAT('.implode(", '	', ", $ridList).') AS rid' : $ridList[0];
				$selects[]=$ridCount>1 ? 'CONCAT('.implode(", '	', ", $bidList).') AS bid' : $bidList[0];
				$selects[]=$ridCount>1 ? 'CONCAT('.implode(", '	', ", $qntList).') AS qnt' : $qntList[0];
			}			
		}
	}

	if ($bid) {
		if ($rids[$ridLast][0]===MEME_RB) $wheres[] = "m{$ridLast}.aid=\"$bid\"";
		else $wheres[] = "m{$ridLast}.bid=\"$bid\"";
	}

	//if ($aid && !$bid && empty($rids)) $wheres=["(aid=\"$aid\" OR bid=\"$aid\")"];

	$wheres[] = "m{$ridLast}.qnt$opr$qnt";

	return [
		'SELECT '.implode(', ', $selects).' FROM '.DB_TABLE_MEME.' m0 '.implode(' ', $joins),
		implode(' AND ', $wheres)
	];
}


// Tokenize DB output
function memeDbDecode ($memeTriples) {
	$memeCommands=[];
	foreach ($memeTriples as $row) {

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

		$memeCommands[]=[[
			[MEME_A, $row['aid']],
			[MEME_RA, $row['rid']],
			[MEME_B, $row['bid']],
			[$opr, $qnt]
		]];
	}
	return $memeCommands;
}

// Output DB data
function memeDBOut ($memeTriples, $set=[]) {
	print memeEncode(memeDbDecode($memeTriples), $set);
}

?>