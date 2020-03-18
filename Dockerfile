FROM php:7.4-cli
RUN apt-get update \
    && apt-get install -y unzip git
COPY --from=composer /usr/bin/composer /usr/bin/composer
RUN composer global require hirak/prestissimo

WORKDIR /app
COPY composer.json /app
COPY composer.lock /app
RUN composer install --no-dev

COPY src/ /app/src
COPY analyze.php /app
ENTRYPOINT [ "php", "analyze.php" ]
CMD []
