<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ERROR | E_PARSE);

require __DIR__.'/meme-sql-conf.php';
require __DIR__.'/meme-sql-lib.php';
require __DIR__.'/meme-parse.php';

?>
<!DOCTYPE HTML>
<html lang="en-US">
<head>
<title>Memelang SQL Demo</title>
<link rel="stylesheet" type="text/css" href="./style.css">
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