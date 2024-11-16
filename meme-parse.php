<?php

global $XCMD, $CMD, $rCMD;

$XCMD = [
	'.' => 1,
	':' => 1
];

define('A', 1);
define('R', 2);
define('B', 4);
define('EQ', 5);


$CMD = [
	'@'	 => A,
	'.'  => R,
	'\'' => 3,
	':'  => B,
	'='  => EQ,
	'>'  => 6,
	'<'  => 7,
	'>=' => 8,
	'<=' => 9,
	'!=' => 10,
	'=>' => 11,
	'==' => 12
];
$rCMD=array_flip($CMD);



// Parse a Memelang query into expressionss
// $pattern = '/^([A-Za-z0-9\_]*)\.?([A-Za-z0-9\_]*):?([A-Za-z0-9\_]*)?([\!<>=]*)?(-?\d*\.?\d*)$/';
function memeDecode ($memelang) {
	global $XCMD, $CMD;

	// Normalize whitespace
	$memelang = preg_replace('/\s+/', ' ', trim($memelang));

	// Remove whitespaces around operators
	$memelang = preg_replace('/\s*([\!<>=]+)\s*/', '$1', $memelang);

	// Prepend decimals with a zero
	$memelang = preg_replace('/([<>=])\.([0-9])/', '${1}0.${2}', $memelang);

	$commands = [];
	$statements = [];
	$expressions = [];
	$chars = str_split($memelang);
	$count = count($chars);

	for ($i = 0; $i < $count; $i++) {
		$varstr = '';

		if (ctype_space($chars[$i])) {
			if (!empty($expressions)) {
				$statements[]=$expressions;
				$expressions=[];
			}
			continue;
		}
		elseif ($chars[$i] === ';') {
			if (!empty($expressions)) {
				$statements[]=$expressions;
				$expressions=[];
			}
			if (!empty($statements)) {
				$commands[]=$statements;
				$statements = [];
			}
		}
		elseif ($chars[$i] === '/' && $chars[$i + 1] === '/') {
			for ($i += 1; $i < $count; $i++) {
				if ($chars[$i] === "\n") break;
			}
		}
		elseif (isset($CMD[$chars[$i]])) {
			$cmdstr='';
			for ($j = 0; $j < 4; $j++) {
				if (isset($CMD[$chars[$i + $j]]) && ($j === 0 || !isset($XCMD[$chars[$i + $j]])))
					$cmdstr .= $chars[$i + $j];
				else {
					$i += $j - 1;
					if (!isset($CMD[$cmdstr])) throw new Exception("CMD not recognized at character $i in $memelang");
					continue 2;
				}
			}
		}
		elseif (ctype_alpha($chars[$i])) {
			for (; $i < $count; $i++) {
				if (preg_match('/[a-zA-Z0-9\_]/', $chars[$i])) $varstr .= $chars[$i];
				else break;
			}
			if (!$cmdstr) $cmdstr = '@';
			$expressions[] = [$CMD[$cmdstr], (string)$varstr];
			$cmdstr='';
			$i--;
		}
		elseif (ctype_digit($chars[$i]) || $chars[$i] === '-') {
			for (; $i < $count; $i++) {
				if (preg_match('/[0-9\.\-]/', $chars[$i])) $varstr .= $chars[$i];
				else break;
			}
			$expressions[] = [$CMD[$cmdstr], (float)$varstr];
			$cmdstr='';
			$i--;
		}
	}

	if (!empty($expressions)) $statements[]=$expressions;
	if (!empty($statements)) $commands[]=$statements;

	return $commands;
}


function memeEncode ($commands, $set=[]) {
	global $CMD, $rCMD;
	$parts=[];
	foreach ($commands as $i=>$statements) {
		$commandArray[$i]='';
		foreach ($statements as $j=>$statement) {
			$statementArray[$j]='';
			foreach ($statement as $exp) {
				$cmdstr = $exp[0]===A ? '' : $rCMD[$exp[0]];
				if ($set['html']) $statementArray[$j].=$cmdstr.'<var class="v'.$exp[0].'">'.htmlspecialchars($exp[1]).'</var>';
				else $statementArray[$j].=$cmdstr.$exp[1];
			}
		}
		$commandArray[$i].=implode(' ', $statementArray);
	}
	if ($set['html']) return '<code class="meme">'.implode(';</code><code class="meme">', $commandArray).';</code>';
	else return implode(';', $commandArray);
}

?>
