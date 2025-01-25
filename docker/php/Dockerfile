# Use the official PHP 7.4 Apache image as the base
FROM php:7.4-apache

# Install system dependencies
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    mariadb-client \
    libpq-dev \
    git \
    unzip \
    libzip-dev \
    zip \
    netcat \
    supervisor \
    pkg-config \
    libssl-dev \
    librabbitmq-dev \
    && docker-php-ext-configure zip \
    && docker-php-ext-install mysqli zip pdo_mysql bcmath sockets pcntl \
    && pecl install redis amqp \
    && docker-php-ext-enable redis amqp \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/composer \
    PATH=/composer/vendor/bin:$PATH

# Set working directory
WORKDIR /var/www/html

# Add these lines before the COPY supervisor configuration step
RUN mkdir -p /var/log/supervisor \
    && touch /var/log/supervisor/supervisord.log \
    && chmod 0777 /var/log/supervisor/supervisord.log

# Copy and prepare entrypoint
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Copy supervisor configuration
# COPY ./docker/supervisor/supervisor.conf /etc/supervisor/supervisord.conf
COPY supervisord.conf /etc/supervisor/supervisord.conf

# Expose port
EXPOSE 80

# Set entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]