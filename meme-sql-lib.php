<?php

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
							if ($memeExpression[0]===MEME_R) $lookups[$memeExpression[1]]=MEME_R;
							else if ($memeExpression[0]===MEME_RI) $lookups[$memeExpression[1]]=MEME_R;

							// work on this
							else if ($memeStatement[$k+1][1]==='is' && $memeStatement[$k+2][1]==='rel') $lookups[$memeExpression[1]]=MEME_R;
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
				if ($lookups[$word]===MEME_R || $lookups[$word]===MEME_RI) {
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
			elseif ($exp[0] === MEME_R) $rids[] = $exp[1];
			elseif ($exp[0] === MEME_B) $bid = $exp[1];
		}

		$trueCount++;
		$trueGroup[$aid][implode("\t", $rids)][$bid][] = $memeStatement;
	}

	// Get all
	if ($querySettings['all'] && $trueCount===0 && $falseCount===0 && $orCount===0)
		return "SELECT * FROM $table";

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
					list($select, $where)=memeSelectWhere($memeStatement);
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
			$orSQL[]=implode(' WHERE ', memeSelectWhere($memeStatement))
				.($cteCount>0 ? ' AND m0.aid IN (SELECT aid FROM z'.($cteCount-1).')' : '');
		}
		$cteSQL[$cteCount] = "z$cteCount AS (".implode(' UNION ALL ', $orSQL).')';
		$cteOut[]=$cteCount;
	}

	// Process NOT conditions
	if ($falseCount) {
		if ($trueCount<1) throw new Exception('A query with a false statements must contain at least one non-OR true statement.');

		$falseSQL=[];
		foreach ($falseGroup as $memeStatement) $falseSQL[]="aid NOT IN (". implode(' WHERE ', memeSelectWhere($memeStatement, true)).')';

		$fsql="SELECT aid FROM z$cteCount WHERE ".implode(' AND ', $falseSQL);
		$cteSQL[++$cteCount] = "z$cteCount AS ($fsql)";
	}


	$selectSQL=[];

	// select all data related to the matching As
	if ($querySettings['all']) {
		$selectSQL[]="SELECT * FROM $table WHERE aid IN (SELECT aid FROM z{$cteCount})";
		$selectSQL[]='SELECT bid AS aid, CONCAT("\'", rid), aid as bid, qnt FROM '."$table WHERE bid IN (SELECT aid FROM z{$cteCount})";
	}
	else if ($cteCount===0) {
		return substr($cteSQL[0], strpos($cteSQL[0],'(')+1, -1);
	}

	// otherwise select the matching and the GET fields
	else {
		foreach ($getGroup as $memeStatement)
			$selectSQL[]=implode(' WHERE ', memeSelectWhere($memeStatement))." AND m0.aid IN (SELECT aid FROM z{$cteCount})";
	}

	foreach ($cteOut as $cteNum)
		$selectSQL[]="SELECT * FROM z{$cteNum}" .($cteNum===$cteCount ? '' : " WHERE aid IN (SELECT aid FROM z{$cteCount})");

	return 'WITH '.implode(', ', $cteSQL).' '.implode(' UNION ALL ', $selectSQL);
}


// Translate an $memeStatement array of ARBQ into an SQL WHERE clause
function memeSelectWhere($memeStatement, $aidOnly=false) {
	global $rOPR;

	$wheres = [];
	$joins=[];
	$selects=['m0.aid as aid'];
	$rids = ['m0.rid'];
	$bids = ['m0.bid'];
	$qnts = ['m0.qnt'];
	$m=0;
	$opr = '!=';
	$qnt = 0;
	$joinPrev='m0.bid';

	foreach ($memeStatement as $i=>$exp) {

		// A
		if ($exp[0] === MEME_A) {
			$wheres[] = "m0.aid='{$exp[1]}'";

		// R
		} else if ($exp[0] === MEME_R) {
			if ($exp[1]!==NULL) $wheres[] = "m0.rid='{$exp[1]}'";

		// RI
		} else if ($exp[0] === MEME_RI) {

			// flip the prior A to a B
			$selects[0] = MEME_BID_AS_AID;
			if ($i>0) $wheres[0] = 'm0.bid="'.$memeStatement[$i-1][1].'"';

			if ($exp[1]!==NULL) $wheres[] = "m0.rid=\"{$exp[1]}\"";
			$rids[0] = "CONCAT(\"'\", m0.rid)";
			$bids[0] = 'm0.aid';
		}

		// B
		else if ($exp[0] === MEME_B) {

			// inverse
			if ($memeStatement[$i-1][0] === MEME_RI || $memeStatement[$i-1][0] === MEME_BB) {
				$wheres[] = "m$m.aid='{$exp[1]}'";
			}
			else {
				$wheres[] = "m$m.bid='{$exp[1]}'";
			}
		}

		// Q
		else if ($exp[0]>=MEME_EQ_BEG && $exp[0]<=MEME_EQ_END) {
			$opr = $exp[0]===MEME_DEQ ? '=' : $rOPR[$exp[0]];
			$qnt = $exp[1];
		}

		// JOINS
		else {
			$lm=$m;
			$m++;
			$wheres[] = "m$m.rid=\"{$exp[1]}\"";
			$wheres[] = "m$lm.qnt!=0";
			
			if ($exp[0] === MEME_BA) {
				$joins[] = "JOIN meme m$m ON ".end($bids)."=m$m.aid";
				$rids[] = "m$m.rid";
				$bids[] = "m$m.bid";
				$qnts[] = "m$m.qnt";
				$joinPrev = "m$m.bid";
			} else if ($exp[0] === MEME_BB) {
				$joins[] = "JOIN meme m$m ON ".end($bids)."=m$m.bid";
				$rids[] = "CONCAT(\"'\", m$m.rid)";
				$bids[] = "m$m.aid";
				$qnts[] = "(CASE WHEN m$m.qnt = 0 THEN 0 ELSE 1 / m$m.qnt END)";
			} else if ($exp[0] === MEME_RA) {
				$joins[]="JOIN meme m$m ON m$lm.rid=m$m.aid";
				$rids[] = "CONCAT(\"?\", m$m.rid)";
				$bids[] = "m$m.bid";
				$qnts[] = "m$m.qnt";
			} else if ($exp[0] === MEME_RB) {
				$joins[]="JOIN meme m$m ON m$lm.rid=m$m.bid";
				$rids[] = "CONCAT(\"'\", m$m.rid)";
				$bids[] = "m$m.aid";
				$qnts[] = "(CASE WHEN m$m.qnt = 0 THEN 0 ELSE 1 / m$m.qnt END)";
			} else throw new Exception('Error: unknown operator');
		}
	}

	// last qnt
	$wheres[] = "m{$m}.qnt$opr$qnt";


	if ($aidOnly) {}
	else if ($m===0 && strpos($bids[0],'.aid')===false) $selects=['*'];
	else if ($m===0) {
		$selects[]=$rids[0].' AS rid';
		$selects[]=$bids[0].' AS bid';
		$selects[]=$qnts[0].' AS qnt';
	}
	else {
		$selects[]='CONCAT('.implode(", '	', ", $rids).') AS rid';
		$selects[]='CONCAT('.implode(", '	', ", $bids).') AS bid';
		$selects[]='CONCAT('.implode(", '	', ", $qnts).') AS qnt';
	}

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
			[MEME_R, $row['rid']],
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