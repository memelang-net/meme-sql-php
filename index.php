<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ERROR | E_PARSE);

require __DIR__.'/meme-sql-conf.php';
require __DIR__.'/meme-sql-lib.php';

?>
<!DOCTYPE HTML>
<html lang="en-US">
<head>
<title>Memelang SQL Querier</title>
<style type="text/css">
body { line-height:150%; background:rgb(11,11,11); color:rgb(230,230,230); font-family:sans-serif; margin:60px 0 100px 0; }
a { color:rgb(170,170,192); text-decoration:none; }
a:hover { text-decoration:underline; }
main { max-width: 700px; width: 94%; margin:0 auto; }
pre.code { display: block; padding:20px; font-size:1.1rem; margin-block-end:2.5em; border-left:4px solid rgb(85,85,85); font-family:monospace; font-size:1rem; background:rgb(43,43,43); white-space:pre-wrap; }
pre.code.sql { color:rgb(0,170,0); font-style:italic; }

table { width:100%; margin-bottom:2.5em; }
th { text-align:left; background:rgb(43,43,43); padding:6px; }
td { background:rgb(36,36,36); padding:6px; }
td.a {width:30%;}
td.r {width:30%;}
td.b {width:30%;}
th.q, td.q {width:10%; text-align: right; }

</style>
</head>
<body>
<main>

<form style="display: block; margin-block-end:2.5em;">
	<input type="text" name="q" value="<?=htmlentities($_GET['q'])?>" style="font-size:2rem;width:75%;" id="q"/>
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
    echo "// SQL\n".memeSQL($_GET['q']) . ';';
} catch (Exception $e) {
    echo $e->getMessage();
}
?></pre>

<?php } ?>

<pre class="code" style="color:rgb(213,128,85);">// HELP WITH MEMELANG QUERIES
  
// Show all data in this DB
<a href="?q=GET%20ALL">GET ALL</a>

//// Use partial A.R:B=Q statements for query clauses ////

// All about George Washington
<a href="?q=george_washington">george_washington</a>

// Which presidents had children?
<a href="?q=.child">.child</a>

// Which presidents were lawyers?
<a href="?q=:lawyer">:lawyer</a>

// Which presidents attended Harvard for college?
<a href="?q=.college:harvard">.college:harvard</a>

// Who was the tenth president?
<a href="?q=.pres_order%23=10">.pres_order#=10</a>


//// Separate AND expressions with spaces ////

// Which presidents attended Columbia and were lawyers?
<a href="?q=.college:columbia_university%20.occupation:lawyer">.college:columbia .occupation:lawyer</a>

// Who were the twentieth through thirtieth presidents?
<a href="?q=.pres_order%3E=20%20.pres_order%3C=30">.pres_order>=20 .pres_order<=30</a>

//// Specify NOT clauses ////

// Which presidents did NOT have children?
<a href="?q=.pres_order%3E0%20NOT%20.child">.pres_order>0 NOT .child</a>


//// Return additional relations with GET ////

// Who were the spouses of the twentieth through thirtieth presidents?
<a href="?q=.pres_order%3E=20%20.pres_order%3C=30%20GET%20.spouse">.pres_order>=20 .pres_order<=30 GET .spouse</a>

// Use GET ALL to return relation to the returned As:
<a href="?q=.college:harvard%20NOT%20.college:columbia%20GET%20ALL">.college:harvard NOT .college:columbia GET ALL</pre></a>


</main>

<script type="text/javascript">
	document.getElementById('q').focus();
</script>

</body>
</html>