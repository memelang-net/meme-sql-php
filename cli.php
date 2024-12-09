<?php

// /php/php C:\Users\Superuser\Sync\Web\meme-sql-php\12\cli.php

require __DIR__.'/meme-parse.php';
require __DIR__.'/meme-sql-conf.php';
require __DIR__.'/meme-sql-lib.php';

print_r(memeDecode($argv[1]));

print "\n";
print memeEncode(memeDecode($argv[1]));
print "\n";
print memeEncode(memeDecode($argv[1]), array('short'=>1));

print "\n";
print memeSQL($argv[1]);


print "\n\n\n";

?>