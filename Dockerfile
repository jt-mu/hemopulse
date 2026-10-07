FROM php:8.2-apache

# Copy all project files into Apache web root
COPY . /var/www/html/

# Enable mod_rewrite for Apache routing if needed
RUN a2enmod rewrite

EXPOSE 80
