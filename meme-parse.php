<?php

define('MEME_FALSE', 0);
define('MEME_TRUE',  1);
define('MEME_A',     2);
define('MEME_R',     3);
define('MEME_B',     5);
define('MEME_EQ',    6);
define('MEME_DEQ',  16);
define('MEME_GET',  40);
define('MEME_ORG',  41);

global $OPR, $xOPR, $rOPR;

$OPR = [
	'@'	 => MEME_A,
	'.'  => MEME_R,
	'\'' => 4,
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
function memeDecode($memelang) {
	global $xOPR, $OPR;

	// Normalize and clean input
	$memelang = preg_replace('/\s+/', ' ', trim($memelang));
	$memelang = preg_replace('/\s*([\!<>=]+)\s*/', '$1', $memelang);
	$memelang = preg_replace('/([<>=])(\-?)\.([0-9])/', '${1}${2}0.${3}', $memelang);

	if ($memelang === '' || $memelang === ';') 
		throw new Exception("Error: Empty query provided.");

	$commands = [];
	$statements = [];
	$expressions = [];
	$chars = str_split($memelang);
	$count = count($chars);
	$bFound = false;
	$oprstr = '';

	for ($i = 0; $i < $count; $i++) {
		$varstr = '';

		switch (true) {
			// Space separates statements
			case ctype_space($chars[$i]):
				$bFound = false;
				if (!empty($expressions)) {
					$statements[] = $expressions;
					$expressions = [];
				}
				break;

			// Semicolon separates commands
			case $chars[$i] === ';':
				$bFound = false;
				if (!empty($expressions)) {
					$statements[] = $expressions;
					$expressions = [];
				}
				if (!empty($statements)) {
					$commands[] = $statements;
					$statements = [];
				}
				break;

			// Comments start with double slash
			case $chars[$i] === '/' && $chars[$i + 1] === '/':
				while (++$i < $count && $chars[$i] !== "\n");
				break;

			// Operators
			case isset($OPR[$chars[$i]]):
				$oprstr = '';
				for ($j = 0; $j < 4 && isset($chars[$i + $j]); $j++) {
					if (isset($OPR[$chars[$i + $j]]) && ($j === 0 || !isset($xOPR[$chars[$i + $j]]))) {
						$oprstr .= $chars[$i + $j];
					} else break;
				}
				if (!isset($OPR[$oprstr])) 
					throw new Exception("Operator '$oprstr' not recognized at character $i in $memelang");
				if ($bFound && $oprstr === '.') 
					throw new Exception("Invalid R after B at $i in $memelang");
				$i += strlen($oprstr) - 1;
				break;

			// Words (A-Z identifiers)
			case ctype_alpha($chars[$i]):
				while ($i < $count && preg_match('/[a-zA-Z0-9_]/', $chars[$i]))
					$varstr .= $chars[$i++];

				$i--; // Adjust for last increment

				if ($oprstr === '=') {

					// =t
					if ($varstr === 't') $expressions[] = [MEME_EQ, MEME_TRUE];

					// =f
					elseif ($varstr === 'f') $expressions[] = [MEME_EQ, MEME_FALSE];

					// =tn OR grouping
					elseif (preg_match('/t(\d+)$/', $varstr, $tm)) {
						$expressions[] = [MEME_EQ, MEME_TRUE];
						$expressions[] = [MEME_ORG, (int)$tm[1]];
					}

				// <>=quantity
				} else {
					$expressions[] = [$oprstr ? $OPR[$oprstr] : MEME_A, (string)$varstr];
					if ($oprstr && $OPR[$oprstr] === MEME_B) $bFound = true;
				}
				$oprstr = '';
				break;

			// Numbers (integers or decimals)
			case ctype_digit($chars[$i]) || $chars[$i] === '-':
				while ($i < $count && preg_match('/[0-9.\-]/', $chars[$i]))
					$varstr .= $chars[$i++];

				$i--; // Adjust for last increment
				$oprstr = $oprstr === '=' ? '#=' : $oprstr;
				$expressions[] = [$OPR[$oprstr], (float)$varstr];
				$oprstr = '';
				break;

			default:
				throw new Exception("Unexpected character '{$chars[$i]}' at position $i in $memelang");
		}
	}

	// Finalize parsing
	if (!empty($expressions)) $statements[] = $expressions;
	if (!empty($statements)) $commands[] = $statements;

	return $commands;
}


function memeEncode($commands, $set = []) {
	global $OPR, $rOPR;

	// Initialize the result array for encoded commands
	$commandArray = [];

	foreach ($commands as $i => $statements) {
		$statementArray = [];

		// Process each statement within a command
		foreach ($statements as $statement) {
			$encodedStatement = '';

			// Process each expression within a statement
			foreach ($statement as $exp) {
				// Determine the operator string (empty for MEME_A)
				$oprstr = $exp[0] === MEME_A ? '' : $rOPR[$exp[0]];

				// Handle special cases for operator "=" (e.g., true/false values)
				if ($oprstr === '=') {
					$exp[1] = ($exp[1] === 0) ? 'f' : (($exp[1] === 1) ? 't' : $exp[1]);
				}

				// Handle special case for operator "#=" (decimal comparison)
				elseif ($oprstr === '#=') {
					$oprstr = '=';
					if (strpos((string)$exp[1], '.') === false)
						$exp[1] = (string)$exp[1] . '.0';
				}

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
	if (!empty($set['html'])) return '<code class="meme">' . implode(';</code><code class="meme">', $commandArray) . ';</code>';
	else return implode(';', $commandArray);
}


?>
