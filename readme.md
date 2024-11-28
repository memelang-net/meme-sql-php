# meme-sql-php

These PHP scripts receive [Memelang](https://memelang.net/) queries, convert them to SQL, then execute them on an SQLite, MySQL, or Postgres database (according to your configuration). Licensed under [Memelicense.net](https://memelicense.net/). Contact info@memelang.net.

Try the demo at http://demo.memelang.net/

## Files
* *data.sql* sample ARBQ data in SQL format
* *data.sqlite* sample ARBQ data in an SQLite binary file
* *index.php* HTML interface to make queries
* *meme-parse.php* parses Memelang commands into an array
* *meme-sql-conf.php* configuration file to establish database connection
* *meme-sql-lib.php* library to convert Memelang to SQL and execute on database