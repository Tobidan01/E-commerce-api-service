FROM php:8.3-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev zip unzip curl \
    && docker-php-ext-install pdo pdo_mysql zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# STEP 1: Copy ONLY composer files first (for caching)
COPY composer.json composer.lock ./

# STEP 2: Install dependencies
RUN composer install --no-dev --optimize-autoloader

# STEP 3: Copy the rest of your app
COPY . .

# STEP 4: Rebuild autoload AFTER app is copied
RUN composer dump-autoload -o

# Apache config
RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
    </Directory>' > /etc/apache2/conf-available/app.conf \
    && a2enconf app

# Permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Start script
RUN echo '#!/bin/bash\n\
    PORT="${PORT:-80}"\n\
    sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf\n\
    sed -i "s/*:80/*:$PORT/" /etc/apache2/sites-available/000-default.conf\n\
    apache2-foreground' > /start.sh && chmod +x /start.sh

CMD ["/start.sh"]