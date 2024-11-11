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
</style>
</head>
<body>
<main>

<form style="display: block; margin-block-end:2.5em;">
	<input type="text" name="q" value="<?=htmlentities($_GET['q'])?>" style="font-size:2rem;width:75%;" id="q"/>
	<input type="submit" name="" value="Go" style="font-size:2rem; width:20%; float: right;" />
</form>

<?php if ($_GET['q']) { ?>
<pre class="code sql"><?php 
try {
    echo memeSQL($_GET['q']) . ';';
} catch (Exception $e) {
    echo $e->getMessage();
}
?></pre>

<pre class="code"><?php print memeOut(memeQuery($_GET['q'])); ?></pre>

<?php } ?>

<pre class="code" style="color:rgb(213,128,85);">// HELP WITH MEMELANG QUERIES
  
// Show all data in this DB
<a href="?q=GET%20ALL">GET ALL</a>

// Use partial A.R:B=Q statements for query clauses:
<a href="?q=ant">ant</a>
<a href="?q=.admire">.admire</a>
<a href="?q=:amsterdam">:amsterdam</a>
<a href="?q=ant.believe">ant.believe</a>
<a href="?q=ant:cairo">ant:cairo</a>
<a href="?q=.explore:bangkok">.explore:bangkok</a>
<a href="?q=.letter%3E1">.letter>1</a>
<a href="?q=.letter:ord%3E=2">.letter:ord>=2</a>
<a href="?q=.letter%3C3">.letter<3</a>
<a href="?q=.letter%3C=4">.letter<=4</a>
<a href="?q=.letter%23=5">.letter#=5</a>

// Separate AND expressions with spaces:
<a href="?q=.letter%3E1%20.admire">.letter>1 .admire</a>
<a href="?q=.letter%3C6%20.admire%20:cairo">.letter<6 .admire :cairo</a>

// Specify NOT clauses:
<a href="?q=.letter%3E1 .admire NOT .explore">.letter>1 .admire NOT .explore</a>
<a href="?q=.letter%23=2 .admire NOT .explore :dubai">.letter#=2 .admire NOT .explore :dubai</a>

// Return additional relations with GET:
<a href="?q=.letter%23=4 GET .believe">.letter#=4 GET .believe</a>
<a href="?q=.letter%3E=1 .admire NOT .explore GET .believe">.letter>=1 .admire NOT .explore GET .believe</a>
<a href="?q=.letter%3C=7 .admire NOT .explore GET .believe :dubai">.letter<=7 .admire NOT .explore GET .believe :dubai</a>

// Use GET ALL to return relation to the returned As:
<a href="?q=.letter>1 .admire NOT .explore GET ALL">.letter>1 .admire NOT .explore GET ALL</pre></a>


</main>

<script type="text/javascript">
	document.getElementById('q').focus();
</script>

</body>
</html>