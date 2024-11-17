<?php

define('MEME_FALSE', 0);
define('MEME_TRUE', 1);
define('MEME_A', 2);
define('MEME_R', 3);
define('MEME_B', 5);
define('MEME_EQ', 6);
define('MEME_DEQ', 16);
define('MEME_GET', 40);
define('MEME_ORG', 41);

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
// $pattern = '/^([A-Za-z0-9\_]*)\.?([A-Za-z0-9\_]*):?([A-Za-z0-9\_]*)?([\!<>=]*)?(-?\d*\.?\d*)$/';
function memeDecode ($memelang) {
	global $xOPR, $OPR;

	// Normalize whitespace
	$memelang = preg_replace('/\s+/', ' ', trim($memelang));

	// Remove whitespaces around operators
	$memelang = preg_replace('/\s*([\!<>=]+)\s*/', '$1', $memelang);

	// Prepend decimals with a zero
	$memelang = preg_replace('/([<>=])(\-?)\.([0-9])/', '${1}${2}0.${3}', $memelang);

	if ($memelang === '' || $memelang === ';') throw new Exception("Error: Empty query provided.");

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
				if ($varstr==='t') $expressions[] = [MEME_EQ, MEME_TRUE];
				else if ($varstr==='f') $expressions[] = [MEME_EQ, MEME_FALSE];
				else if (preg_match('/t(\d+)$/', $varstr, $tm)) {
					$expressions[] = [MEME_EQ, MEME_TRUE];
					$expressions[] = [MEME_ORG, (int)$tm[1]];
				}
			}
			else {
				if (!$oprstr) $expressions[] = [MEME_A, (string)$varstr];
				else $expressions[] = [$OPR[$oprstr], (string)$varstr];

				if ($OPR[$oprstr]===MEME_B) $bFound=true;
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
		$statementArray=[];
		$commandArray[$i]='';
		foreach ($statements as $j=>$statement) {
			$statementArray[$j]='';
			foreach ($statement as $exp) {
				$oprstr = $exp[0]===MEME_A ? '' : $rOPR[$exp[0]];

				if ($oprstr==='=') {
					if ($exp[1]===0) $exp[1]='f';
					if ($exp[1]===1) $exp[1]='t';
				}

				else if ($oprstr==='#=') {
					$oprstr='=';
					if (strpos($exp[1], '.') === false) $exp[1]=(string)$exp[1] . '.0';
				}

				if ($set['html']) $statementArray[$j].=$oprstr.'<var class="v'.$exp[0].'">'.htmlspecialchars($exp[1]).'</var>';
				else $statementArray[$j].=$oprstr.$exp[1];
			}
		}
		$commandArray[$i].=implode(' ', $statementArray);
	}
	if ($set['html']) return '<code class="meme">'.implode(';</code><code class="meme">', $commandArray).';</code>';
	else return implode(';', $commandArray);
}

?>
