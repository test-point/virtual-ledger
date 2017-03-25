# virtual-ledger
repo for a php application that will act as a virtual ledger, using the testpoint services.

Set up cronjob:

*/5 * * * * php /var/www/virtual-ledger-a schedule:run >> /dev/null 2>&1

Dependencies:

1) gnupg2
sudo apt-get install gnupg2 -y
 
2) rng-tools
sudo apt-get install rng-tools
vi /etc/default/rng-tools
and add the line HRNGDEVICE=/dev/urandom: