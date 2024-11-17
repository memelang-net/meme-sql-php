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
<title>Memelang SQL Querier</title>
<link rel="stylesheet" type="text/css" href="./style.css">
</head>
<body>
<main>

<form style="display: block; margin-block-end:2.5em;">
	<input type="text" name="q" value="<?=htmlentities($_GET['q'])?>" style="font-size:2rem;width:75%;" id="q" placeholder="Enter query"/>
	<input type="submit" name="" value="Go" style="font-size:2rem; width:20%; float: right;" />
</form>

<?php if ($_GET['q']) { ?>

<table>
	<tr><th class="a">A</th><th class="r">.R</th><th class="b">:B</th><th class="q">=Q</th></tr>
	<?php 
		$rows=memeQuery($_GET['q']);
		foreach ($rows as $row) {
			print "<tr>";
			print '<td class="a"><a href="?q='.htmlentities($row['aid']).'">'.$row['aid'].'</a></td>';
			print '<td class="r"><a href="?q=.'.htmlentities($row['rid']).'">.'.$row['rid'].'</a></td>';
			print '<td class="b"><a href="?q=:'.htmlentities($row['bid']).'">:'.$row['bid'].'</a></td>';
			print '<td class="q">='.$row['qnt'].'</td>';
			print '</tr>';
		}
	?>
</table>

<pre class="code sql"><?php 
try {
    echo "/* SQL */\n".memeSQL($_GET['q']) . ';';
} catch (Exception $e) {
    echo $e->getMessage();
}
?></pre>

<?php } ?>



<table>
<tr>
	<th colspan="2"><b>Example Queries</b> (<a href="//memelang.net/">Help</a>)</th>
</tr>

<tr>
	<td class="code"><a href="?q=qry.all">qry.all</a></td>
	<td class="expl">Show all data in this DB.</td>
</tr>

<tr>
	<td class="code"><a href="?q=george_washington">george_washington</a></td>
	<td class="expl">All about George Washington.</td>
</tr>

<tr>
	<td class="code"><a href="?q=.child">.child</a></td>
	<td class="expl">Who were children of the presidents?</td>
</tr>

<tr>
	<td class="code"><a href="?q=:lawyer">:lawyer</a></td>
	<td class="expl">Which presidents were lawyers?</td>
</tr>

<tr>
	<td class="code"><a href="?q=.college:harvard">.college:harvard</a></td>
	<td class="expl">Which presidents attended Harvard?</td>
</tr>

<tr>
	<td class="code"><a href="?q=.college%20.college:harvard%3Df">.college .college:harvard=f</a></td>
	<td class="expl">Which presidents did NOT attended Harvard?</td>
</tr>

<tr>
	<td class="code"><a href="?q=.pres_order%3D10">.pres_order=10</a></td>
	<td class="expl">Who was the tenth president?</td>
</tr>

<tr>
	<td class="code"><a href="?q=.college:columbia%20.occupation:lawyer">.college:columbia .occupation:lawyer</a></td>
	<td class="expl">Which presidents attended Columbia and were lawyers?</td>
</tr>

<tr>
	<td class="code"><a href="?q=.pres_order%3E%3D20%20.pres_order%3C%3D30">.pres_order&gt;=20 .pres_order&lt;=30</a></td>
	<td class="expl">Who were the twentieth through thirtieth presidents?</td>
</tr>

<tr>
	<td class="code"><a href="?q=.pres_order%20.child%3Df">.pres_order .child=f</a></td>
	<td class="expl">Which presidents did NOT have children?</td>
</tr>

<tr>
	<td class="code"><a href="?q=.pres_order%3E%3D20%20.pres_order%3C%3D30%20.spouse%3Dg">.pres_order&gt;=20 .pres_order&lt;=30 .spouse=g</a></td>
	<td class="expl">Who were the spouses of the twentieth through thirtieth presidents?</td>
</tr>

<tr>
	<td class="code"><a href="?q=.college:harvard%20.college:columbia%20qry.all">.college:harvard .college:columbia qry.all</a></td>
	<td class="expl">Use <code>qry.all</code> to return all memes related to the matching As.</td>
</tr>

<tr>
	<td class="code"><a href="?q=james_carter;%20ronald_reagan">james_carter; ronald_reagan</a></td>
	<td class="expl">Get all about James Carter as well as Ronald Reagan</td>
</tr>

</table>

</main>

<script type="text/javascript">
	document.getElementById('q').focus();
</script>

</body>
</html>