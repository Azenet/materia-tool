FROM php:7.4-apache

WORKDIR /
RUN apt-get update -y && apt-get install -y libssh-dev librabbitmq-dev gnupg2 libzip-dev
RUN docker-php-ext-install iconv pdo_mysql bcmath sockets zip
RUN echo 'date.timezone = Europe/Paris' >> ${PHP_INI_DIR}/conf.d/timezone.ini

RUN curl -LSs https://getcomposer.org/installer > composer.php
RUN php composer.php
RUN rm composer.php

RUN curl -sL https://deb.nodesource.com/setup_10.x | bash -

RUN curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add -
RUN echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list

RUN apt-get update -y && apt-get install -y yarn nodejs

RUN mkdir /app
WORKDIR /app
COPY composer.json .
COPY composer.lock .
COPY package.json .
COPY yarn.lock .

RUN php /composer.phar install -o
RUN yarn install

ENV APACHE_DOCUMENT_ROOT /app/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY . .
RUN yarn encore production

RUN a2enmod rewrite
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

#RUN php /composer.phar dump-autoload --no-dev --classmap-authoritative

RUN mkdir -p /app/var/{cache,log} && chown 33:33 /app/var

ENTRYPOINT docker-php-entrypoint apache2-foreground
