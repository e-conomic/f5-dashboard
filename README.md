f5-dashboard
============
This is a simple PHP package that can be used to get pools and nodes with status from an F5 LTM load balancer.

It is configured in config.php (see config.php-dist for more info). It uses the concept of environments to manage configuration for host, username, password and administrative partition.

Once config.php has been configured, the index.php must be called with ?env=[environment]. Using the config.php-dist example, it would be either ?env=staging or ?env=production

Screenshot
==========
![Screenshot of the dashboard](http://imgur.com/paacfwo "Screenshot of the dashboard")

To-do
=====
* better handling of states
* cleanup CSS for states
