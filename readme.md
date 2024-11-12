# meme2sql

These PHP scripts receive [Memelang](https://memelang.net/) queries, convert them to SQL, then execute them on an SQLite, MySQL, or Postgres database (according to your configuration). Licensed under [Memelicense.net](https://memelicense.net/).

## Files
* *data.sql* sample ARBQ data in SQL format
* *data.sqlite* sample ARBQ data in an SQLite binary file
* *meme-sql-api.php* recevies a GET request, requires the other PHP files, returns result
* *meme-sql-conf.php* configuration file to establish database connection
* *meme-sql-lib.php* library to convert Memelang to SQL and execute on database

## Demo
Try the demo at http://demo.memelang.net/