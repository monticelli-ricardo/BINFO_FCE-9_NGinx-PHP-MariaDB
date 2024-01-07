# Exercise 2 - COVID-19 data analysis:
- Part 1 - PHP terminal script that downloads and parses ALL required CSV files from the given URL and inserts all the data into the right DB tables.

- Part 2 - Web application (accessible under "http://localhost:8080/exercise2") that then uses this DB to allow users sending information the following request to the DB: output the number of confirmed / recovered cases and deaths in a given time period (more than 1 day is possible) for an input-defined country/region (the entered country name should be considered as a substring of the given country name inside the data). Make sure that user input data are validated and that no SQL injections are possible.
----
## Exercise 2 - Procedure

- Part 1 - Step 1 - Delete a Docker Compose stack.
    docker-compose -p docker_stack_name down

- Part 1 - Step 2 - Update and save the Docker compose [nginx-php-mariadb] stack YAML file, to have a shared volume for the containers. 
	                  Include in each service (php, nginx, DB) under the "volume" section the line:
	
	  services:
      nginx:
        ... # same configuration
        volumes:
            - ./www:/var/www/html
            - ./shared_files:/shared_files  # Shared volume among containers 

      php:
        ... # same configuration 
        volumes:
            - ./www:/var/www/html 
            - ./shared_files:/shared_files  # Shared volume among containers

      DB:
        ... # same configuration 
        volumes:
            - ./dbdata:/var/lib/mysql
            - ./shared_files:/shared_files  # Shared volume among containers
        ... # same configuration 
    
    ... # same configuration 

- Part 1 - Step 3 - Build up the docker compose stack
	  docker-compose up -d

- Part 1 - Step 4 - Run the script 'csv_download.php' via:
  (A.) the local terminal command: docker exec -it docker-nginx-php-mariadb-php-1 php /var/www/html/exercise2/csv_download.php
  (B.) the local website 'http://localhost:8080/exercise2/csv_download.php'

- Part 1 - Step 5 - Run the script 'csv_insertion.php' via:
  (A.) the local terminal command: docker exec -it docker-nginx-php-mariadb-php-1 php /var/www/html/exercise2/csv_insertion.php 
  (B.) the local website 'http://localhost:8080/exercise2/csv_insertion.php'

- Part 2 - Step 6 - Reach the web application COVID19 statistics http://localhost:8080/exercise2/exercise2.php and execute any search


----
### Optional - Read the exercise log files
  - For the CSV extraction and insertion, check out the file: logFile.log
  - For the Web Application, check out the file: webLogFile.log

### Optional - Grant privileges to the webprog DB user
    docker exec -it docker-nginx-php-mariadb-DB-1 mysql -h DB -u root -p   
    Enter password: 

    MariaDB [(none)]> use webprog
    Reading table information for completion of table and column names
    You can turn off this feature to get a quicker startup with -A

    Database changed
    MariaDB [webprog]> GRANT ALL PRIVILEGES ON *.* TO 'webprog'@'%' WITH GRANT OPTION;
    Query OK, 0 rows affected (0.007 sec)

    MariaDB [webprog]> FLUSH PRIVILEGES;
    Query OK, 0 rows affected (0.002 sec)

### Optional - Move the files to the MariaDB application volume, run the below command in your local terminal
    docker exec docker-nginx-php-mariadb-DB-1 sh -c 'cp /shared_files/*.csv /var/lib/mysql/webprog'

