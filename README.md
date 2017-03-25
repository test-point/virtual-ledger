# virtual-ledger
repo for a php application that will act as a virtual ledger, using the testpoint services.

Set up cronjob:

* * * * * for i in 0 1 2 3 4 5 6 7 8 9 10 11; do php /var/www/virtual-ledger-a/artisan transactions & sleep 5; done; php /var/www/virtual-ledger-a/artisan transactions

Dependencies:

1) gnupg2
sudo apt-get install gnupg2 -y
 
2) rng-tools
sudo apt-get install rng-tools
vi /etc/default/rng-tools
and add the line HRNGDEVICE=/dev/urandom: