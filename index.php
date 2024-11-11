<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ERROR | E_PARSE);

require __DIR__.'/meme-sql-conf.php';
require __DIR__.'/meme-sql-lib.php';

if (!$_GET['q']) $_GET['q']='.letter>0 || *';

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
.sql { color:rgb(0,170,0); font-style:italic; }
</style>
</head>
<body>
<main>

<form style="display: block; margin-bottom: 30px;">
	<input type="text" name="q" value="<?=htmlentities($_GET['q'])?>" style="font-size:2rem;width:75%;" id="q"/>
	<input type="submit" name="" value="Go" style="font-size:2rem; width:20%; float: right;" />
</form>

<pre class="code sql"><?php print memeSQL($_GET['q']).';'; ?></pre>

<pre class="code"><?php print memeOut(memeQuery($_GET['q'])); ?></pre>

<pre class="code" style="color:rgb(213,128,85);">Use partial A.R:B=Q statements for query clauses:
  ant
  .admire
  :amsterdam
  ant.believe
  ant:cairo
  .explore:bangkok
  .letter>1
  .letter:ord>=2
  .letter<3
  .letter<=4
  .letter#=5

Separate AND cluases with spaces:
  .letter>1 .admire
  .letter<6 .admire :cairo

Put NOT clauses after a first vertical bar <code>|</code>:
  .letter>1 .admire | .explore
  .letter#=2 .admire | .explore :dubai

List more expressions to return after a second vertical bar <code>|</code>:
  .letter>=1 .admire | .explore | .believe
  .letter<=7 .admire | .explore | .believe :dubai

Use * to get every relation to the returned As:
  .letter>1 .admire | .explore | *</pre>


</main>

<script type="text/javascript">
	document.getElementById('q').focus();
</script>

</body>
</html>