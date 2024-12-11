# meme-sql-php
These PHP scripts receive [Memelang](https://memelang.net/) queries, convert them to SQL, then execute them on an SQLite, MySQL, or Postgres database (according to your configuration). Licensed under [Memelicense.net](https://memelicense.net/). Contact info@memelang.net.

Try the demo at https://demo.memelang.net/


## Installation

Installation on Ubuntu for SQLite:

	# Install packages
	sudo apt install -y php sqlite3 git php-sqlite3
	
	# Download files
	git clone https://github.com/memelang-net/meme-sql-php.git
	cd meme-sql-php
	
	# Create database
	cat ./data.sql | sqlite3 ./data.sqlite
	
	# Execute in CLI
	php ./meme.php "john_adams.child"


## Example usage

	# php ./meme.php "john_adams.child"

	SQL: SELECT * FROM meme m0  WHERE m0.aid='john_adams' AND m0.rid='child' AND m0.qnt!=0
	
	+---------------------+---------------------+---------------------+------------+
	| A                   | R                   | B                   |          Q |
	+---------------------+---------------------+---------------------+------------+
	| john_adams          | child               | abigail_adams_smit  |          1 |
	| john_adams          | child               | charles_adams       |          1 |
	| john_adams          | child               | john_quincy_adams   |          1 |
	| john_adams          | child               | thomas_boylston_ad  |          1 |
	+---------------------+---------------------+---------------------+------------+


## Files
* *data.sql* sample ARBQ data in SQL format
* *meme.php* CLI interface to make queries
* *meme-db.php* configuration file to establish database connection
* *meme-parse.php* library to parse Memelang commands into an array
* *meme-sql.php* library to convert Memelang to SQL queries
