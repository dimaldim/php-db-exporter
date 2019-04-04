# MySQL DB Exporter made with PHP

This simple PHP class file exports entire MySQL database as separated files. Each table -> one file. (gzip)

Also it has the capability to limit table rows(by default it is 10,000 rows). If there are more rows than the limit, the script will separate the file into pieces.

## Getting Started

### Installing

You could download or copy the source code of **db_class.php**

### Sample usage

The script below will save exported data to **backups** directory. Inside it will create another directory in format: ***dbname_year_month_day_hour_min***

```php
<?php
require_once 'db_class.php';
$db_user = 'my_user';
$db_pass = 'my_pass';
$db_host = 'my_host';
$db_name = 'my_db';

error_reporting(E_ALL);

set_time_limit(900);
ini_set('memory_limit', '-1');

$backup = new Export_DB($db_host, $db_user, $db_pass, $db_name, '', 'backups');
$result = $backup->backupTables();
if($result)
{
 echo "Script executed OK";
}
```

## Built With

* [PHP](https://www.php.net/)

## Contributing

Please feel free to post issues and pull requests in order to make it better.

## Authors

* **Dimitar Dimitrov** - *Initial work*

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE) file for details

## Donate

I've been working for free and for my pleasure. If you feel good to donate my work, I'll be glad for that:

[![paypal](https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif)](https://www.paypal.me/ddimitrov92)
