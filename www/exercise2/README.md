How can my code be run for the respective tasks below:
- PHP terminal script that downloads and parses ALL required CSV files from the given URL and inserts all the data into the right DB tables. 
- Web application (accessible under "http://localhost:8080/exercise2") that then uses this DB to allow users sending information the following request to the DB: output the number of confirmed / recovered cases and deaths in a given time period (more than 1 day is possible) for an input-defined country/region (the entered country name should be considered as a substring of the given country name inside the data). 
  Make sure that user input data are validated and that no SQL injections are possible.

-- Grant privileges to the webprog DB user
PS C:\University\BINFO - Web programing\Docker Compose\docker-nginx-php-mariadb> docker exec -it 705b9e5eb79e13a1798b69a236dc203e767c4413f9ffabda984faa0339b324af mysql -h DB -u root -p   
Enter password: 

MariaDB [(none)]> use webprog
Reading table information for completion of table and column names
You can turn off this feature to get a quicker startup with -A

Database changed
MariaDB [webprog]> GRANT ALL PRIVILEGES ON *.* TO 'webprog'@'%' WITH GRANT OPTION;
Query OK, 0 rows affected (0.007 sec)

MariaDB [webprog]> FLUSH PRIVILEGES;
Query OK, 0 rows affected (0.002 sec)