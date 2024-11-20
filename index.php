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
    echo $e->getMessage();
}

?>

<table>
	<tr><th colspan="4">Results</th></tr>
	<tr><th class="a">A</th><th class="r">.R</th><th class="b">:B</th><th class="q">=Q</th></tr>
	<?php 
		$rows=memeQuery($_GET['q']);
		foreach ($rows as $row) {
			print "<tr>";
			print '<td class="a meme"><a href="?q='.htmlentities($row['aid']).'"><var class="v2">'.$row['aid'].'</var></a></td>';
			print '<td class="r meme"><a href="?q=.'.htmlentities($row['rid']).'">.<var class="v3">'.$row['rid'].'</var></a></td>';
			print '<td class="b meme"><a href="?q=:'.htmlentities($row['bid']).'">:<var class="v5">'.$row['bid'].'</var></a></td>';
			print '<td class="q meme">=<var class="v16">'.$row['qnt'].'</var></td>';
			print '</tr>';
		}
	?>
</table>


<table>
	<tr><th>SQL Query</th></tr>
	<tr><td style="padding:12px"><code class="meme code sql"><?php echo $sql; ?></code></td></tr>
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
<tr>
	<th>Show all data in this DB.</th>
</tr>
<tr>
	<td class="code spa"><a href="?q=qry.all"><code class="meme"><var class="v2">qry</var>.<var class="v3">all</var></code></a></td>
</tr>

<tr>
	<th>All about George Washington.</th>
</tr>
<tr>
	<td class="code spa"><a href="?q=george_washington"><code class="meme"><var class="v2">george_washington</var></code></a></td>
</tr>

<tr>
	<th>Who were children of the presidents?</th>
</tr>
<tr>
	<td class="code spa"><a href="?q=.child"><code class="meme">.<var class="v3">child</var></code></a></td>
</tr>

<tr>
	<th>Which presidents were members of the Whig party?</th>
</tr>
<tr>
	<td class="code spa"><a href="?q=.party:whig"><code class="meme">.<var class="v3">party</var>:<var class="v5">whig</var></code></a></td>
</tr>

<tr>
	<th>Which presidents attended Harvard?</th>
</tr>
<tr>
	<td class="code spa"><a href="?q=.college:harvard"><code class="meme">.<var class="v3">college</var>:<var class="v5">harvard</var></code></a></td>
</tr>

<tr>
	<th>Which presidents were born before 1820?</th>
</tr>
<tr>
	<td class="code spa"><a href="?q=.birth_year:year%3C1820"><code class="meme">.<var class="v3">birth_year</var>:<var class="v5">year</var>&lt;<var class="v16">1820</var></code></a></td>
</tr>

<tr>
	<th>Which presidents attended Columbia and were lawyers?</th>
</tr>
<tr>
	<td class="code spa"><a href="?q=.college:columbia%20.occupation:lawyer"><code class="meme">.<var class="v3">college</var>:<var class="v5">columbia</var> .<var class="v3">occupation</var>:<var class="v5">lawyer</var></code></a></td>
</tr>


<tr>
	<th>Who were the twentieth through thirtieth presidents?</th>
</tr>
<tr>
	<td class="code spa"><a href="?q=.pres_order%3E%3D20%20.pres_order%3C%3D30"><code class="meme">.<var class="v3">pres_order</var>&gt;=<var class="v13">20</var> .<var class="v3">pres_order</var>&lt;=<var class="v15">30</var></code></a></td>
</tr>

<tr>
	<th>Which presidents attended a college that was NOT Harvard?</th>
</tr>
<tr>
	<td class="code spa"><a href="?q=.college%20.college:harvard%3Df"><code class="meme">.<var class="v3">college</var> .<var class="v3">college</var>:<var class="v5">harvard</var>=<var class="v6">f</var></code></a></td>
</tr>

<tr>
	<th>Which presidents did NOT have children?</th>
</tr>
<tr>
	<td class="code spa"><a href="?q=.pres_order%20.child%3Df"><code class="meme">.<var class="v3">pres_order</var> .<var class="v3">child</var>=<var class="v6">f</var></code></a></td>
</tr>

<tr>
	<th>Who were the spouses of the twentieth through thirtieth presidents?</th>
</tr>
<tr>
	<td class="code spa"><a href="?q=.pres_order%3E%3D20%20.pres_order%3C%3D30%20.spouse%3Dg"><code class="meme">.<var class="v3">pres_order</var>&gt;=<var class="v13">20</var> .<var class="v3">pres_order</var>&lt;=<var class="v15">30</var> .<var class="v3">spouse</var></code></a></td>
</tr>

<tr>
	<th>Use <i>qry.all</i> to return all memes related to the matching As.</th>
</tr>
<tr>
	<td class="code spa"><a href="?q=.college:harvard%20.college:columbia%20qry.all"><code class="meme">.<var class="v3">college</var>:<var class="v5">harvard</var> .<var class="v3">college</var>:<var class="v5">columbia</var> <var class="v2">qry</var>.<var class="v3">all</var></code></a></td>
</tr>

<tr>
	<th>Get all about James Carter as well as Ronald Reagan.</th>
</tr>
<tr>
	<td class="code spa"><a href="?q=james_carter;%20ronald_reagan"><code class="meme"><var class="v2">james_carter</var>; <var class="v2">ronald_reagan</var></code></a></td>
</tr>

<tr>
	<th>Which presidents were lawyers that attended Harvard <u>or</u> William and Mary?</th>
</tr>
<tr>
	<td class="code spa"><a href="?q=.occupation:lawyer%20.college:harvard=t1%20.college:william_and_mary=t1"><code class="meme">.<var class="v3">occupation</var>:<var class="v5">lawyer</var> .<var class="v3">college</var>:<var class="v5">harvard</var>=<var class="v6">t</var><var class="v41">1</var> .<var class="v3">college</var>:<var class="v5">william_and_mary</var>=<var class="v6">t</var><var class="v41">1</var></code></a></td>
</tr>


<tr>
	<th>Which presidents has children that became presidents?</th>
</tr>
<tr>
	<td class="code spa"><a href="?q=.child.pres_order"><code class="meme">.<var class="v3">child</var>.<var class="v3">pres_order</var></code></a></td>
</tr>

</table>


</main>

<script type="text/javascript">
	document.getElementById('q').focus();
</script>

</body>
</html>