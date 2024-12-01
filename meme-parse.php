<?php

define('MEME_FALSE', 0);
define('MEME_TRUE',  1);
define('MEME_A',     2);
define('MEME_RB',    3);
define('MEME_RA',    4);
define('MEME_RR',    6);
define('MEME_B',     5);
define('MEME_EQ',    7);
define('MEME_DEQ',  16);
define('MEME_GET',  40);
define('MEME_ORG',  41);
define('MEME_TERM', 99);


global $OPR, $xOPR, $rOPR;

$OPR = [
	'@'	 => MEME_A,
	'.'  => MEME_RA,
	'\'' => MEME_RB,
	'?' => MEME_RR,
	':'  => MEME_B,
	'='  => MEME_EQ,
//	'==' => 8,
	'=>' => 9,
	'!=' => 10,
//	'!==' => 11,
	'>'  => 12,
	'>=' => 13,
	'<'  => 14,
	'<=' => 15,
 	'#=' => MEME_DEQ,
];
$rOPR=array_flip($OPR);

$xOPR = [
	'.' => 1,
	':' => 1,
	'-' => 1,
];

// Parse a Memelang query into expressionss
function memeDecode($memeString) {
	global $xOPR, $OPR;

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
	$bFound = 0;
	$oFound = 0;
	$opid = MEME_A;
	$oprstr = '';

	for ($i = 0; $i < $count; $i++) {
		$varstr = '';

		switch (true) {
			// Semicolon separates commands
			case $chars[$i] === ';':
				$opid = MEME_A;
				$bFound = 0;
				$oFound = 0;
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
				$bFound = 0;
				$oFound = 0;
				if (!empty($memeExpressions)) {
					$memeStatements[] = $memeExpressions;
					$memeExpressions = [];
				}
				break;

			// Comments start with double slash
			case $chars[$i] === '/' && $chars[$i + 1] === '/':
				while (++$i < $count && $chars[$i] !== "\n");
				break;

			// Operators
			case isset($OPR[$chars[$i]]):

				if ($opid === MEME_RA) $memeExpressions[] = [MEME_RA, NULL];
				else if ($opid === MEME_RB) $memeExpressions[] = [MEME_RB, NULL];
				else if ($i>0 && $opid>0) throw new Exception("Extraneous operator at char $i in $memeString");

				$oprstr = '';
				for ($j = 0; $j < 4 && isset($chars[$i + $j]); $j++) {
					if (isset($OPR[$chars[$i + $j]]) && ($j === 0 || !isset($xOPR[$chars[$i + $j]]))) {
						$oprstr .= $chars[$i + $j];
					} else break;
				}
				
				if (!isset($OPR[$oprstr])) throw new Exception("Operator '$oprstr' not recognized at char $i in $memeString");

				$opid = $OPR[$oprstr];

				if ($bFound && $opid < MEME_EQ) throw new Exception("Errant operator after B at char $i in $memeString");

				if ($opid >= MEME_EQ && ++$oFound>1) throw new Exception("Double operator at char $i in $memeString");

				if ($opid === MEME_B) $bFound = 1;

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
					}

				// <>=quantity
				} else {
					$memeExpressions[] = [$opid, (string)$varstr];
				}
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
