#!/bin/bash -xe

printenv | sed 's;^\(.[^=]*\)=\(.*\)$;export \1="\2";g' > /env

/etc/init.d/cron start

cd /var/www/html

touch /var/www/html/storage/logs/laravel.log
chmod 666 /var/www/html/storage/logs/laravel.log

chmod 700 .gnupg
chmod 600 .gnupg/*

[[ ! -f ".gnupg/gpg-agent.conf" ]] && echo "allow-loopback-pinentry" > .gnupg/gpg-agent.conf
[[ ! -f ".gnupg/gpg.conf" ]] && echo "pinentry-mode loopback" > .gnupg/gpg.conf

#php artisan db:create
php artisan --force migrate

exec "${@}"
