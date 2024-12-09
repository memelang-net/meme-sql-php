<?php

define('MEME_FALSE', 0);
define('MEME_TRUE',  1);
define('MEME_A',     2);
define('MEME_RI',    3);
define('MEME_R',     4);
define('MEME_B',     5);

define('MEME_EQ',    8);
define('MEME_DEQ',   9);

define('MEME_GET',  40);
define('MEME_ORG',  41);
define('MEME_RA',   42);
define('MEME_BB',   43);
define('MEME_BA',   44);
define('MEME_RB',   45);

define('MEME_EQ_BEG',    MEME_EQ);
define('MEME_EQ_END',    17);

define('MEME_TERM', 99);

global $OPR, $OPRCHAR, $rOPR;

$OPR = [
	'@'	   => MEME_A,
	'.'    => MEME_R,
	'\''   => MEME_RI,
	':'    => MEME_B,
	'='    => MEME_EQ,
 	'#='   => MEME_DEQ,
	'=='   => 10,
	'=>'   => 11,
	'!='   => 12,
	'!=='  => 13,
	'>'    => 14,
	'>='   => 15,
	'<'    => 16,
	'<='   => 17,
	'?'    => MEME_RA,
	'[ra]' => MEME_RA,
	'?\''  => MEME_RB,
	'[rb]' => MEME_RB,
	'[ba]' => MEME_BA,
	'[bb]' => MEME_BB,
];
$rOPR=array_flip($OPR);

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

// Parse a Memelang query into expressionss
function memeDecode($memeString) {
	global $OPRCHAR, $OPR;

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
	$oprFound = [MEME_A=>0,MEME_R=>0,MEME_B=>0,MEME_EQ=>0];
	$opid = MEME_A;
	$oprstr = '';

	for ($i = 0; $i < $count; $i++) {
		$varstr = '';

		switch (true) {
			// Semicolon separates commands
			case $chars[$i] === ';':
				$opid = MEME_A;
				$oprFound = [MEME_A=>0,MEME_R=>0,MEME_B=>0,MEME_EQ=>0];
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
				$opid = MEME_A;
				$oprFound = [MEME_A=>0,MEME_R=>0,MEME_B=>0,MEME_EQ=>0];
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

				if (!($opid = $OPR[$oprstr])) throw new Exception("Operator $oprstr not recognized at char $i in $memeString");

				$i+=3;
				if ($chars[$i]==='.') $i++; // extraneous dot in [xx].
				break;

			// Operators
			case isset($OPR[$chars[$i]]):

				// previous operator was followed by empty string
				if ($opid === MEME_R) $memeExpressions[] = [MEME_R, NULL];
				else if ($opid === MEME_RI) $memeExpressions[] = [MEME_RI, NULL];
				//else if ($i>0 && $opid>MEME_A) throw new Exception("Extraneous operator at char $i in $memeString");

				$oprstr = '';
				for ($j = 0; $j < 3 && isset($chars[$i + $j]); $j++) {
					if (isset($OPR[$chars[$i + $j]]) && ($j === 0 || $OPRCHAR[$chars[$i + $j]]===2)) {
						$oprstr .= $chars[$i + $j];
					} else break;
				}

				if (!isset($OPR[$oprstr])) throw new Exception("Operator $oprstr not recognized at char $i in $memeString");

				$opid = $OPR[$oprstr];

				// tally used operators
				if ($opid===MEME_R || $opid===MEME_RI) {
					$oprFound[MEME_R]++;
					if ($oprFound[MEME_B]>0) throw new Exception("Errant R after B at char $i in $memeString");
				}
				else if ($opid>=MEME_EQ_BEG && $opid<=MEME_EQ_END) {
					if (++$oprFound[MEME_EQ]>1) throw new Exception("Redundant Q operator at char $i in $memeString");
				} elseif ($opid===MEME_A || $opid===MEME_B) $oprFound[$opid]++;

				$i += strlen($oprstr) - 1;
				break;

			// Words (A-Z identifiers)
			case ctype_alpha($chars[$i]):
				while ($i < $count && preg_match('/[a-zA-Z0-9_]/', $chars[$i]))
					$varstr .= $chars[$i++];

				$i--; // Adjust for last increment

				if ($opid === MEME_EQ) {

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
				} elseif ($opid === MEME_R  && $oprFound[MEME_R]>1) {
					$memeExpressions[] = [MEME_BA, (string)$varstr];

				// .R'R
				} elseif ($opid === MEME_RI  && $oprFound[MEME_R]>1) {
					$memeExpressions[] = [MEME_BB, (string)$varstr];

				// @A .R :B
				} else $memeExpressions[] = [$opid, (string)$varstr];
				
				$opid = null;
				break;

			// Numbers (integers or decimals)
			case ctype_digit($chars[$i]) || $chars[$i] === '-':
				while ($i < $count && preg_match('/[0-9.\-]/', $chars[$i]))
					$varstr .= $chars[$i++];

				$i--; // Adjust for last increment
				if ($opid===MEME_EQ) $opid=MEME_DEQ;
				$memeExpressions[] = [$opid, (float)$varstr];
				$opid = null;
				break;

			default:
				throw new Exception("Unexpected character '{$chars[$i]}' at char $i in $memeString");
		}
	}

	// Finalize parsing
	if (!empty($memeExpressions)) $memeStatements[] = $memeExpressions;
	if (!empty($memeStatements)) $memeCommands[] = $memeStatements;

	return $memeCommands;
}


function memeEncode($memeCommands, $set = []) {
	global $OPR, $rOPR;

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
				elseif (!empty($set['short']) && $exp[0] === MEME_BA) $oprstr='.';

				// shorten [bb] to '
				elseif (!empty($set['short']) && $exp[0] === MEME_BB) $oprstr='\'';

				// shorten [ra] to ?
				elseif (!empty($set['short']) && $exp[0] === MEME_RA) $oprstr='?';

				// shorten [rb] to ?'
				elseif (!empty($set['short']) && $exp[0] === MEME_RB) $oprstr='?\'';

				else $oprstr = $rOPR[$exp[0]];


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
	if (!empty($set['html'])) return '<code class="meme">' . implode(';</code> <code class="meme">', $commandArray) . ';</code>';
	else return implode('; ', $commandArray);
}


?>
