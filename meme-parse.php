<?php

define('MEME_FALSE', 0);
define('MEME_TRUE',  1);
define('MEME_UNK',   2);

define('MEME_A',     3);
define('MEME_B',     4);

define('MEME_RI',    5);
define('MEME_R',     6);

define('MEME_EQ',    8);
define('MEME_DEQ',   9);
define('MEME_NEQ',   10);

define('MEME_LST',   11);
define('MEME_GRE',   12);
define('MEME_LSE',   13);
define('MEME_GRT',   14);

define('MEME_BA',    20);
define('MEME_BB',    21);
define('MEME_RA',    22);
define('MEME_RB',    23);

define('MEME_GET',   30);
define('MEME_ORG',   31);

define('MEME_TERM',  99);

global $OPRINT, $OPRCHAR, $OPRSTR, $OPRSHORT;

$OPRINT = [
	'@'	   => MEME_A,
	':'    => MEME_B,
	'.'    => MEME_R,
	'\''   => MEME_RI,

	'='    => MEME_EQ,
 	'#='   => MEME_DEQ,
	'!='   => MEME_NEQ,
	'>'    => MEME_GRT,
	'>='   => MEME_GRE,
	'<'    => MEME_LST,
	'<='   => MEME_LSE,

	'?'    => MEME_RA,
	'[ra]' => MEME_RA,
	'?\''  => MEME_RB,
	'[rb]' => MEME_RB,
	'[ba]' => MEME_BA,
	'[bb]' => MEME_BB,
];
$OPRSTR=array_flip($OPRINT);


$OPRCHAR = [
	'.' => 1,
	':' => 1,
	"'" => 1,
//	'-' => 1,
	'?' => 1,
//	'[' => 2,
	'=' => 2,
	'!' => 2,
	'#' => 2,
	'>' => 2,
	'<' => 2,
];

$OPRSHORT = [
	MEME_BA => '.',
	MEME_BB => '\'',
	MEME_RA => '?',
	MEME_RB => '?\'',
];


// Parse a Memelang query into expressionss
function memeDecode($memeString) {
	global $OPRCHAR, $OPRINT;

	// Normalize and clean input
	$memeString = preg_replace('/\s+/', ' ', trim($memeString));
	$memeString = preg_replace('/\s*([\!<>=]+)\s*/', '$1', $memeString);
	$memeString = preg_replace('/([<>=])(\-?)\.([0-9])/', '${1}${2}0.${3}', $memeString);

	if ($memeString === '' || $memeString === ';') 
		throw new Exception("Error: Empty query provided.");

	$memeCommands = [];
	$memeStatements = [];
	$memeExpressions = [];
	$chars = str_split($memeString);
	$count = count($chars);
	$oprFound = [MEME_A=>0,MEME_B=>0,MEME_R=>0,MEME_EQ=>0,MEME_RA=>0];
	$oprid = MEME_A;
	$oprgrp = MEME_A;
	$oprstr = '';

	for ($i = 0; $i < $count; $i++) {
		$varstr = '';

		switch (true) {
			// Semicolon separates commands
			case $chars[$i] === ';':
				$oprid = MEME_A;
				$oprgrp = MEME_A;
				$oprFound = [MEME_A=>0,MEME_B=>0,MEME_R=>0,MEME_EQ=>0,MEME_RA=>0];
				if (!empty($memeExpressions)) {
					$memeStatements[] = $memeExpressions;
					$memeExpressions = [];
				}
				if (!empty($memeStatements)) {
					$memeCommands[] = $memeStatements;
					$memeStatements = [];
				}
				break;

			// Space separates statements
			case ctype_space($chars[$i]):
				$oprid = MEME_A;
				$oprgrp = MEME_A;
				$oprFound = [MEME_A=>0,MEME_B=>0,MEME_R=>0,MEME_EQ=>0,MEME_RA=>0];
				if (!empty($memeExpressions)) {
					$memeStatements[] = $memeExpressions;
					$memeExpressions = [];
				}
				break;

			// Comments start with double slash
			case $chars[$i] === '/' && $chars[$i + 1] === '/':
				while (++$i < $count && $chars[$i] !== "\n");
				break;

			// [xx]
			case $chars[$i] === '[':

				$oprstr=substr($memeString, $i, 4);

				if (!($oprid = $OPRINT[$oprstr])) throw new Exception("Operator $oprstr not recognized at char $i in $memeString");

				$oprgrp = $oprid;

				$i+= ($chars[$i]==='.') ? 4 : 3; // extraneous dot in [xx].
				break;

			// Operators
			case isset($OPRINT[$chars[$i]]):

				// previous operator was followed by empty string
				if ($oprid === MEME_R) $memeExpressions[] = [MEME_BA, NULL];
				else if ($oprid === MEME_RI) $memeExpressions[] = [($oprFound[MEME_R]>1?MEME_BB:MEME_RI), NULL];

				$oprstr = '';
				for ($j = 0; $j < 3 && isset($chars[$i + $j]); $j++) {
					if (isset($OPRINT[$chars[$i + $j]]) && ($j === 0 || $OPRCHAR[$chars[$i + $j]]===2)) {
						$oprstr .= $chars[$i + $j];
					} else break;
				}

				if (!isset($OPRINT[$oprstr])) throw new Exception("Operator $oprstr not recognized at char $i in $memeString");

				$oprid  = $OPRINT[$oprstr];

				switch (true) {
					case $oprid <= MEME_B: $oprgrp = $oprid; break;
					case $oprid <= MEME_R: $oprgrp = MEME_R; break;
					case $oprid <= MEME_LSE: $oprgrp = MEME_EQ; break;
					default: $oprgrp = MEME_RA; break;
				}

				$oprFound[$oprgrp]++;

				// error checks
				if ($oprgrp === MEME_R && $oprFound[MEME_B]>0) throw new Exception("Errant R after B at char $i in $memeString");

				if ($oprgrp === MEME_EQ && $oprFound[MEME_EQ]>1) throw new Exception("Extraneous equality operator at char $i in $memeString");

				$i += $j - 1;
				break;

			// Words (A-Z identifiers)
			case ctype_alpha($chars[$i]):
				while ($i < $count && preg_match('/[a-zA-Z0-9_]/', $chars[$i]))
					$varstr .= $chars[$i++];

				$i--; // Adjust for last increment

				if ($oprid === MEME_EQ) {

					// =t
					if ($varstr === 't') $memeExpressions[] = [MEME_EQ, MEME_TRUE];

					// =f
					elseif ($varstr === 'f') $memeExpressions[] = [MEME_EQ, MEME_FALSE];

					// =g
					elseif ($varstr === 'g') $memeExpressions[] = [MEME_EQ, MEME_GET];

					// =tn OR grouping
					elseif (preg_match('/t(\d+)$/', $varstr, $tm)) {
						$memeExpressions[] = [MEME_EQ, MEME_TRUE];
						$memeExpressions[] = [MEME_ORG, (int)$tm[1]];
					
					// something else
					} else throw new Exception("Unrecognized =Q at char $i in $memeString");

				// .R.R
				} elseif ($oprid === MEME_R  && $oprFound[MEME_R]>1)
					$memeExpressions[] = [MEME_BA, (string)$varstr];

				// .R'R
				elseif ($oprid === MEME_RI  && $oprFound[MEME_R]>1)
					$memeExpressions[] = [MEME_BB, (string)$varstr];

				// @A .R :B
				else $memeExpressions[] = [$oprid, (string)$varstr];
				
				$oprid = 0;
				$oprgrp = 0;
				break;

			// Numbers (integers or decimals)
			case ctype_digit($chars[$i]) || $chars[$i] === '-':
				while ($i < $count && preg_match('/[0-9.\-]/', $chars[$i]))
					$varstr .= $chars[$i++];

				if (!preg_match('/^-?\d*(\.\d+)?$/', $varstr))
					throw new Exception("Malformed number $varstr at char $i in $memeString");

				$i--; // Adjust for last increment
				if ($oprid===MEME_EQ) $oprid=MEME_DEQ;
				$memeExpressions[] = [$oprid, (float)$varstr];
				$oprid = 0;
				break;

			default:
				throw new Exception("Unexpected character '{$chars[$i]}' at char $i in $memeString");
		}
	}

	// Finalize parsing
	if ($oprid === MEME_RI) $memeExpressions[] = [($oprFound[MEME_R]>1?MEME_BB:MEME_RI), NULL];
	if ($oprid === MEME_R && $oprFound[MEME_R]>1) $memeExpressions[] = [MEME_BA, NULL];
	if (!empty($memeExpressions)) $memeStatements[] = $memeExpressions;
	if (!empty($memeStatements)) $memeCommands[] = $memeStatements;

	return $memeCommands;
}


function memeEncode($memeCommands, $set = []) {
	global $OPRSTR, $OPRSHORT;

	// Initialize the result array for encoded commands
	$commandArray = [];

	foreach ($memeCommands as $i => $memeStatements) {
		$statementArray = [];

		// Process each statement within a command
		foreach ($memeStatements as $statement) {
			$encodedStatement = '';

			// Process each expression within a statement
			foreach ($statement as $exp) {

				// Determine the operator string (empty for MEME_A)
				if ($exp[0] === MEME_A) $oprstr='';

				// Handle special cases for operator "=" (e.g., true/false values)
				else if ($exp[0] === MEME_EQ) {
					$oprstr='=';
					if ($exp[1] === MEME_FALSE) $exp[1] = 'f';
					elseif ($exp[1] === MEME_TRUE) $exp[1] = 't';
					else if ($exp[1] === MEME_GET) $exp[1] = 'g';
				}

				// Handle special case for operator "#=" (decimal comparison)
				elseif ($exp[0] === MEME_DEQ) {
					$oprstr = '=';
					if (strpos((string)$exp[1], '.') === false)
						$exp[1] = (string)$exp[1] . '.0';
				}

				// shorten [ba] to .
				elseif (!empty($set['short']) && !empty($OPRSHORT[$exp[0]])) $oprstr=$OPRSHORT[$exp[0]];

				else $oprstr = $OPRSTR[$exp[0]];


				// Append the encoded expression to the statement

				// HTML color-coded
				if (!empty($set['html'])) $encodedStatement .= htmlspecialchars($oprstr) . '<var class="v' . $exp[0] . '">' . htmlspecialchars($exp[1]) . '</var>';

				// Plain text
				else $encodedStatement .= $oprstr . $exp[1];
			}

			// Add the fully encoded statement to the statement array
			$statementArray[] = $encodedStatement;
		}

		// Combine all statements for the current command into a single string
		$commandArray[$i] = implode(' ', $statementArray);
	}

	// Return all commands as a semicolon-separated string (optionally in HTML format)
	if (!empty($set['html'])) return '<code class="meme">' . implode(';</code> <code class="meme">', $commandArray) . '</code>';
	else return implode('; ', $commandArray);
}


// Convert tuples to memelang
function memeStringify ($rows) {
	$str='';
	foreach ($rows as $row) {
		$str.=$row[COL_AID]
		.(strpos($row[COL_RID],"'")===0 ? '' : '.').$row[COL_RID]
		.':'.$row[COL_BID]
		.'='.$row[COL_QNT]
		.';';
	}
	return $str;
}




?>
