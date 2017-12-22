#!/bin/bash -xe

printenv | sed 's;^\(.[^=]*\)=\(.*\)$;export \1="\2";g' > /env

/etc/init.d/cron start
/etc/init.d/rng-tools start

cd /var/www/html

chown -R www-data .gnupg storage

chmod 700 .gnupg

find .gnupg -type d -exec chmod 700 {} +
find .gnupg -type f -exec chmod 600 {} +

[[ ! -f ".gnupg/gpg-agent.conf" ]] && echo "allow-loopback-pinentry" > .gnupg/gpg-agent.conf
[[ ! -f ".gnupg/gpg.conf" ]] && echo "pinentry-mode loopback" > .gnupg/gpg.conf

#php artisan db:create
php artisan --force migrate

exec "${@}"
