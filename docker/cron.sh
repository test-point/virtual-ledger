#!/bin/bash
source /env
cd /var/www/html
php artisan virtual_ledger:load-messages
php artisan providers:load-transactions
php artisan providers:load-approved-transactions
php artisan providers:load-paid-transactions
php artisan providers:send-messages