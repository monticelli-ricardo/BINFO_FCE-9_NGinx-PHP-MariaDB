# Docker Image for NGinx-PHP-MariaDB #

This directory provides a Docker composed application, which combines

* NGinx as web server,
* PHP fpr dynamic web generation,
* MariaDB as database server,
* PHPMyAdmin as web-based database administration tool.

## Building and running the image ##

Building the image is straightforward, simply run _docker compose build_. Then you can run the application with the command _docker compose up -d_. For the DB-related examples, the database dump in _DB.dump_ have to be loaded into the database (easiest with a local MariaDB client, or with the PHPMyAdmin website). Their are two provided URLs:

* http://localhost:8080 gives access to different directories with the code examples.
* http://localhost:8081 gives access to the PHPMyAdmin application. You can login with the account _webprog_ and password _webprog_.
