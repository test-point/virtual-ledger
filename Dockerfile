FROM php:7.1-apache

RUN echo 'APT::Default-Release "jessie";' >> /etc/apt/apt.conf.d/default-release && \
    echo 'deb http://deb.debian.org/debian stretch main'>> /etc/apt/sources.list

RUN apt-get update

RUN apt-get --no-install-recommends install -y php-pear php5-dev rng-tools php5-mysql libgpgme11-dev git wget unzip cron && \
    apt-get --no-install-recommends install -y -t stretch gnupg2 wget libassuan-dev libgpg-error-dev

RUN wget -O /usr/local/bin/composer https://getcomposer.org/composer.phar && chmod +x /usr/local/bin/composer

RUN wget --no-check-certificate -O gpgme.tar.bz2 https://www.gnupg.org/ftp/gcrypt/gpgme/gpgme-1.9.0.tar.bz2 && tar -xjf gpgme.tar.bz2 && \
    cd gpgme* && ./configure --prefix=/usr && make && make install && cd .. && rm -rf gpgme*

RUN pecl install oauth gnupg \
      && docker-php-ext-enable oauth gnupg

RUN docker-php-ext-install pdo_mysql

RUN echo "HRNGDEVICE=/dev/urandom" >> /etc/default/rng-tools
RUN  /etc/init.d/rng-tools start

RUN a2enmod rewrite

WORKDIR /var/www/html

COPY composer.* ./

RUN composer install --no-scripts --no-autoloader

COPY database database
COPY app app
COPY artisan .
COPY bootstrap bootstrap
COPY config config
COPY phpunit.xml .
COPY public public
COPY resources resources
COPY routes routes
COPY storage storage
COPY tests tests
COPY webpack.mix.js .
COPY .env.example .env

RUN composer dump-autoload --optimize && \
    composer run-script post-install-cmd

RUN php artisan key:generate

RUN chmod 777 bootstrap/cache
RUN find storage -type d -exec chmod 777 {} \;

COPY docker/crontab /etc/cron.d/crontab
COPY docker/cron.sh /

COPY docker/docker-entrypoint.sh /

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

EXPOSE 80
ENTRYPOINT ["/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
