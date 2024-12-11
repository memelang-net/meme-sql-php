<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ERROR | E_PARSE);

require __DIR__.'/meme-db.php';
require __DIR__.'/meme-parse.php';
require __DIR__.'/meme-sql.php';


// CLI

if (php_sapi_name() == "cli") {
	$args=implode('', array_slice($argv, 1, 99));

	echo "\nMEMELANG: ".memeEncode(memeDecode($args),['short'=>1])."\n\n";
	echo 'SQL: '.memeSQL($args)."\n\n";
	echo "RESULTS:\n".str_repeat('-', 78)."\n";
	echo "A                   | R                   | B                    |           Q\n";
	echo str_repeat('-', 78)."\n";

	$rows=memeQuery($args);

	if (empty($rows)) print "No matching memes.";
	else {
		foreach ($rows as &$row) {
			echo str_pad(substr($row[COL_AID],0,18), 20, ' ');
			echo '| '.str_pad(substr($row[COL_RID],0,18), 20, ' ');
			echo '| '.str_pad(substr($row[COL_BID],0,18), 21, ' ');
			echo '| '.str_pad(substr($row[COL_QNT],0,8), 11, ' ', STR_PAD_LEFT); 
			echo "\n";
		}
	}

	exit("\n\n");
}


?>
<!DOCTYPE HTML>
<html lang="en-US">
<head>
<title>Memelang SQL Demo</title>
<style type="text/css">
	body { line-height:150%; background:rgb(11,11,11); color:rgb(230,230,230); font-family:sans-serif; margin:60px 0 100px 0; }
a { color:rgb(170,170,192); text-decoration:none; }
a:hover { text-decoration:underline; }
main { max-width: 700px; width: 94%; margin:0 auto; }
pre { display: block; padding:20px; border-left:4px solid rgb(85,85,85); background:rgb(43,43,43); white-space:pre-wrap; font-size:1rem; }

code { font-size:1rem; }

var { font-style:normal; }
.meme .v3 { color:rgb(0,170,170); } /* A */
.meme .v4 { color:rgb(213,0,213); } /* :B */
.meme .v5 { color:rgb(213,128,0); font-style:italic; } /* 'R */
.meme .v6 { color:rgb(213,128,0); } /* .R */
.meme .v8 { color:rgb(0,192,0); font-style:italic; } /* =Q */
.meme .v9 { color:rgb(0,192,0); } /* #=Q */

.meme .v11,
.meme .v12,
.meme .v13,
.meme .v14,
.meme .v15,
.meme .v16,
.meme .v17 { color:rgb(0,192,0); } /* <=Q */

.meme .v41 { color:rgb(128,170,128); font-style:italic; vertical-align: baseline; font-size:80%; } /* ORG */

.meme .v33 { color:rgb(213,150,0); } /* .R.R */
.meme .v34 { color:rgb(213,150,0); font-style:italic; } /* .R'R */
.meme .v36,
.meme .v35 { color:rgb(213,170,0); } /* ?R */


.off { color:rgb(128,128,128); font-style:italic; }

textarea { width:100%; font-size:1.1rem; box-sizing:border-box; }
input { width:100%; box-sizing:border-box; font-size: 1.1rem; text-align:center; }
form { display:block; margin-bottom:20px; text-align:center; }
form, pre, table, .mbe { width:100%; margin-bottom:20px; box-sizing: border-box; }

th { text-align:left; background:rgb(43,43,43); padding:8px 12px; }
td { background:rgb(36,36,36); padding:8px 12px; }
td.a {width:30%; max-width:166px; overflow:hidden; }
td.r {width:30%; max-width:166px; }
td.b {width:30%; max-width:166px; overflow:hidden; }
th.q, td.q {width:10%; text-align: right; }

td.code { display:block; white-space:pre-wrap; }
td.spa { margin-bottom:6px; }


table.err th { background:rgb(85,21,21); }
table.err td { background:rgb(43,11,11); }

table.sql th { background:rgb(30,43,30);  }
table.sql td { background:rgb(30,36,30); color:rgb(192,230,192); font-style:italic; padding:20px;  }

table.meme th { background:rgb(43,43,30);  }
table.meme td { background:rgb(36,36,30); padding:20px;  }
</style>
</head>
<body>
<main>

<form>
	<textarea name="q" style="height:80px;" id="q" placeholder="Enter Memelang query"><?=htmlentities($_GET['q'])?></textarea><br/>
	<input type="submit" name="" value="Search" />
</form>

<?php if (strlen($_GET['q'])) { ?>

<?php 

try {
	$sql=memeSQL($_GET['q']) . ';';
} catch (Exception $e) {
	print '<table class="err"><tr><th>ERROR</th></tr><tr><td style="padding:12px">'.htmlentities($e->getMessage()).'</td></tr></table>';
}

?>

<table>
	<tr><th colspan="4">Results</th></tr>
	<tr><th class="a">A</th><th class="r">R</th><th class="b">B</th><th class="q">Q</th></tr>
	<?php 
		$rows=memeQuery($_GET['q']);
		foreach ($rows as $row) {

			$inv=(strpos($row[COL_RID],"'")===0);

			//if ($row[COL_AID]==='UNK') continue;

			print "<tr>";
			print '<td class="a meme"><a href="?q='.htmlentities($row[COL_AID]).'+qry.all"><var class="v3">'.$row[COL_AID].'</var></a></td>';

			print '<td class="r meme"><a href="?q='.($inv?'':'.').urlencode($row[COL_RID]).'"><var class="v'.($inv?'5':'6').'">'.htmlentities($row[COL_RID]).'</var></a></td>';

			if ($row[COL_BID]==='UNK')
				print '<td class="b meme"><var class="off">various</var></td>';

			else print '<td class="b meme"><a href="?q='.urlencode($row[COL_BID]).'+qry.all"><var class="v4">'.htmlentities($row[COL_BID]).'</var></a></td>';

			print '<td class="q meme"><var class="v9">'.$row[COL_QNT].'</var></td>';

			print '</tr>';
		}
	?>
</table>

<table class="meme">
	<tr><th>Memelang Query</th></tr>
	<tr><td><code class="meme code"><?php echo memeEncode(memeDecode($_GET['q']), ['short'=>1, 'html'=>1]); ?></code></td></tr>
</table>

<table class="sql">
	<tr><th>SQL Query</th></tr>
	<tr><td><code class="code sql"><?php echo $sql; ?></code></td></tr>
</table>

<?php } ?>


<table>
<tr>
	<th>Memelang SQL Demo</th>
</tr>
<tr>
	<td class="code">This is demonstration of translating <a href="https://memelang.net">Memelang</a> to SQL for querying relational databases. See the <a href="https://github.com/memelang-net/meme-sql-php">PHP code in GitHub</a>. A Python version is coming soon. Example queries:</td>
</tr>
</table>

<table>


<?php

$queries=array(
'qry.all'=>'Show all data in the database.',
'george_washington'=>'All about George Washington.',
'.child'=>'Who were children of the presidents?',
'.party:whig'=>'Which presidents were members of the Whig party?',
'.birth.year:ad<1820'=>'Which presidents were born before 1820?',
'.college:columbia .lawyer'=>'Which presidents attended Columbia and were lawyers?',
'.president_order>=20 .president_order<=30 .spouse'=>'Who were the spouses of the twentieth through thirtieth presidents?',
'.president_order .child=f'=>'Which presidents did not have children?',
'.college:harvard .college:columbia qry.all'=>'Use qry.all to return all memes related to the matching As.',
'james_carter; ronald_reagan'=>'Get all about James Carter as well as Ronald Reagan.',
'.lawyer .college:harvard=t1 .college:william_and_mary=t1'=>'Which presidents were lawyers that attended Harvard or William and Mary?',
'?profession'=>'What were the professions of the presidents?',
'george_washington?profession'=>'What were the professions of George Washington?',
'new_york\'?profession'=>'Which presidents previously worked for New York state?',
'.child.president_order'=>'Which presidents has children that became presidents?'
);

?>

<?php foreach ($queries as $query=>$desc) { ?>
<tr><th><?=$desc?></th></tr>
<tr><td class="code spa"><a href="?q=<?=urlencode($query)?>"><code class="meme"><?=memeEncode(memeDecode($query), ['short'=>1,'html'=>1])?></code></a></td></tr>
<?php } ?>

</table>


</main>

<script type="text/javascript">
	document.getElementById('q').focus();
</script>

</body>
</html>
