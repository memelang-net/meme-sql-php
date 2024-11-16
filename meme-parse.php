<?php

define('F', 0);
define('T', 1);
define('A', 2);
define('R', 3);
define('B', 5);
define('EQ', 6);
define('DEQ', 18);

global $OPR, $xOPR, $rOPR;

$OPR = [
	'@'	 => A,
	'.'  => R,
	'\'' => 4,
	':'  => B,
	'='  => EQ,
//	'==' => 8,
	'=>' => 9,
	'!=' => 10,
//	'!==' => 11,
	'>'  => 12,
	'>=' => 13,
	'<'  => 14,
	'<=' => 15,
 	'#=' => DEQ,
];
$rOPR=array_flip($OPR);

$xOPR = [
	'.' => 1,
	':' => 1,
	'-' => 1,
];


// Parse a Memelang query into expressionss
// $pattern = '/^([A-Za-z0-9\_]*)\.?([A-Za-z0-9\_]*):?([A-Za-z0-9\_]*)?([\!<>=]*)?(-?\d*\.?\d*)$/';
function memeDecode ($memelang) {
	global $xOPR, $OPR;

	// Normalize whitespace
	$memelang = preg_replace('/\s+/', ' ', trim($memelang));

	// Remove whitespaces around operators
	$memelang = preg_replace('/\s*([\!<>=]+)\s*/', '$1', $memelang);

	// Prepend decimals with a zero
	$memelang = preg_replace('/([<>=])(\-?)\.([0-9])/', '${1}${2}0.${3}', $memelang);

	$commands = [];
	$statements = [];
	$expressions = [];
	$chars = str_split($memelang);
	$count = count($chars);
	$bFound = false;
	$oprstr='';

	for ($i = 0; $i < $count; $i++) {
		$varstr = '';

		// space separates statements
		if (ctype_space($chars[$i])) {
			$bFound=false;
			if (!empty($expressions)) {
				$statements[]=$expressions;
				$expressions=[];
			}
			continue;
		}

		// semicolon separates commands
		elseif ($chars[$i] === ';') {
			$bFound=false;
			if (!empty($expressions)) {
				$statements[]=$expressions;
				$expressions=[];
			}
			if (!empty($statements)) {
				$commands[]=$statements;
				$statements = [];
			}
		}

		// comment preceded by double slash
		elseif ($chars[$i] === '/' && $chars[$i + 1] === '/') {
			for ($i += 1; $i < $count; $i++) {
				if ($chars[$i] === "\n") break;
			}
		}

		// operator
		elseif (isset($OPR[$chars[$i]])) {

			$oprstr='';
			for ($j = 0; $j < 4; $j++) {
				if (isset($OPR[$chars[$i + $j]]) && ($j === 0 || !isset($xOPR[$chars[$i + $j]])))
					$oprstr .= $chars[$i + $j];
				else break;
			}

			if (!isset($OPR[$oprstr])) throw new Exception("Operator '$oprstr' not recognized at character $i in $memelang");
			
			if ($bFound && $oprstr==='.') throw new Exception("Invalid R after B at $i in $memelang");

			$i += $j - 1;
		}

		// A-Z word
		elseif (ctype_alpha($chars[$i])) {
			for (; $i < $count; $i++) {
				if (preg_match('/[a-zA-Z0-9\_]/', $chars[$i])) $varstr .= $chars[$i];
				else break;
			}
			
			if ($oprstr==='=') {
				if ($varstr==='t') $expressions[] = [EQ, T];
				else if ($varstr==='f') $expressions[] = [EQ, F];
			}
			else {
				if (!$oprstr) $oprstr = '@';
				$expressions[] = [$OPR[$oprstr], (string)$varstr];

				if ($OPR[$oprstr]===B) $bFound=true;
			}
			$oprstr='';
			$i--;
		}

		// decimal number
		elseif (ctype_digit($chars[$i]) || $chars[$i]==='-') {
			for (; $i < $count; $i++) {
				if (preg_match('/[0-9\.\-]/', $chars[$i])) $varstr .= $chars[$i];
				else break;
			}

			if ($oprstr==='=') $oprstr='#=';

			$expressions[] = [$OPR[$oprstr], (float)$varstr];
			$oprstr='';
			$i--;
		}
	}

	if (!empty($expressions)) $statements[]=$expressions;
	if (!empty($statements)) $commands[]=$statements;

	return $commands;
}


function memeEncode ($commands, $set=[]) {
	global $OPR, $rOPR;
	$parts=[];
	foreach ($commands as $i=>$statements) {
		$commandArray[$i]='';
		foreach ($statements as $j=>$statement) {
			$statementArray[$j]='';
			foreach ($statement as $exp) {
				$oprstr = $exp[0]===A ? '' : $rOPR[$exp[0]];
				if ($set['html']) $statementArray[$j].=$oprstr.htmlspecialchars($exp[1]).'</var>';
				else $statementArray[$j].=$oprstr.$exp[1];
			}
		}
		$commandArray[$i].=implode(' ', $statementArray);
	}
	if ($set['html']) return '<code class="meme">'.implode(';</code><code class="meme">', $commandArray).';</code>';
	else return implode(';', $commandArray);
}

?>
