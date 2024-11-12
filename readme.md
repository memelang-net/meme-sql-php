# meme2sql

These PHP scripts receive [Memelang](https://memelang.net/) queries, convert them to SQL, then execute them on an SQLite, MySQL, or Postgres database (according to your configuration). Licensed under [Memelicense.net](https://memelicense.net/).

## Files
* *data.sql* sample ARBQ data in SQL format
* *data.sqlite* sample ARBQ data in an SQLite binary file
* *meme-sql-api.php* recevies a GET request, requires the other PHP files, returns result
* *meme-sql-conf.php* configuration file to establish database connection
* *meme-sql-lib.php* library to convert Memelang to SQL and execute on database

## Queries

### Show all data in this DB
[GET ALL](http://demo.memelang.net/?q=GET%20ALL)

### Use partial A.R:B=Q statements for query clauses:
[ant](http://demo.memelang.net/?q=ant) 
[.admire](http://demo.memelang.net/?q=.admire) 
[:amsterdam](http://demo.memelang.net/?q=:amsterdam) 
[ant.believe](http://demo.memelang.net/?q=ant.believe) 
[ant:cairo](http://demo.memelang.net/?q=ant:cairo) 
[.explore:bangkok](http://demo.memelang.net/?q=.explore:bangkok) 
[.letter>1](http://demo.memelang.net/?q=.letter%3E1) 
[.letter:ord>=2](http://demo.memelang.net/?q=.letter:ord%3E=2) 
[.letter<3](http://demo.memelang.net/?q=.letter%3C3) 
[.letter<=4](http://demo.memelang.net/?q=.letter%3C=4) 
[.letter#=5](http://demo.memelang.net/?q=.letter%23=5) 

### Separate AND expressions with spaces:
[.letter>1 .admire](http://demo.memelang.net/?q=.letter%3E1%20.admire) 
[.letter<6 .admire :cairo](http://demo.memelang.net/?q=.letter%3C6%20.admire%20:cairo) 

### Specify NOT clauses:
[.letter>1 .admire NOT .explore](http://demo.memelang.net/?q=.letter%3E1%20.admire%20NOT%20.explore) 
[.letter#=2 .admire NOT .explore :dubai](http://demo.memelang.net/?q=.letter%23=2%20.admire%20NOT%20.explore%20:dubai) 

### Return additional relations with GET:
[.letter#=4 GET .believe](http://demo.memelang.net/?q=.letter%23=4%20GET%20.believe) 
[.letter>=1 .admire NOT .explore GET .believe](http://demo.memelang.net/?q=.letter%3E=1%20.admire%20NOT%20.explore%20GET%20.believe) 
[.letter<=7 .admire NOT .explore GET .believe :dubai](http://demo.memelang.net/?q=.letter%3C=7%20.admire%20NOT%20.explore%20GET%20.believe%20:dubai) 

### Use GET ALL to return relation to the returned As:
[.letter>1 .admire NOT .explore GET ALL](http://demo.memelang.net/?q=.letter%3E1%20.admire%20NOT%20.explore%20GET%20ALL) 
