<?php

// For security, make sure this file is NOT executed from the web
if (php_sapi_name() !== "cli") exit("CLI only");


// Load libraries
require __DIR__.'/meme-db.php';
require __DIR__.'/meme-parse.php';
require __DIR__.'/meme-sql.php';


// Convert memelang to SQL
try { $sql=memeSQL($argv[1]); } 
catch (Exception $e) { exit("\n$e\n\n"); }


// Execute query
$rows=memeQuery($argv[1]);


// Output data
echo "\nSQL: $sql\n\n";
echo '+'.str_repeat('-', 21).'+'.str_repeat('-', 21).'+'.str_repeat('-', 21).'+'.str_repeat('-', 12)."+\n";
echo "| A                   | R                   | B                   |          Q |\n";
echo '+'.str_repeat('-', 21).'+'.str_repeat('-', 21).'+'.str_repeat('-', 21).'+'.str_repeat('-', 12)."+\n";

if (empty($rows)) print "| No matching memes".str_repeat(' ', 60)."|\n";
else {
	foreach ($rows as &$row) {
		echo '| '.str_pad(substr($row[COL_AID],0,18), 20, ' ');
		echo '| '.str_pad(substr($row[COL_RID],0,18), 20, ' ');
		echo '| '.str_pad(substr($row[COL_BID],0,18), 20, ' ');
		echo '| '.str_pad(substr($row[COL_QNT],0,8), 10, ' ', STR_PAD_LEFT)." |\n";
	}
}
echo '+'.str_repeat('-', 21).'+'.str_repeat('-', 21).'+'.str_repeat('-', 21).'+'.str_repeat('-', 12)."+\n";

?>