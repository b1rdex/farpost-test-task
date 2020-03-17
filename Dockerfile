FROM php:7.4-cli
RUN apt-get update \
    && apt-get install -y unzip git
COPY --from=composer /usr/bin/composer /usr/bin/composer
RUN composer global require hirak/prestissimo

WORKDIR /app
COPY composer.json /app
COPY composer.lock /app
RUN composer install

COPY . /app
CMD [ "php", "./analyze.php" ]
#CMD [ "vendor/bin/phpunit", "test/Unit" ]
#CMD [ "vendor/bin/phpstan" ]
#CMD [ "vendor/bin/psalm" ]
