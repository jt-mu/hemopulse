FROM php:8.2-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install pdo_mysql mbstring \
    && a2enmod rewrite setenvif \
    && rm -rf /var/lib/apt/lists/*

COPY deployment/render/apache.conf /etc/apache2/conf-available/hemopulse.conf
COPY deployment/render/php.ini /usr/local/etc/php/conf.d/hemopulse.ini
RUN a2enconf hemopulse
WORKDIR /var/www/html
COPY . .
COPY deployment/render/start.sh /usr/local/bin/hemopulse-start
RUN sed -i 's/\r$//' /usr/local/bin/hemopulse-start \
    && chmod +x /usr/local/bin/hemopulse-start

ENV HEMOPULSE_PROFILE_DIR=/var/lib/hemopulse/profiles \
    HEMOPULSE_MAIL_PREVIEW_DIR=/var/lib/hemopulse/mail-previews
EXPOSE 10000
CMD ["/usr/local/bin/hemopulse-start"]
