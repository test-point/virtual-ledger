#!/bin/bash
source /env
cd /var/www/html
php artisan transactions
php artisan load-messages