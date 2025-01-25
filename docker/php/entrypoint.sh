#!/bin/bash
set -e

# Function to log messages with timestamp
log_message() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] $1"
}

error_exit() {
    log_message "ERROR: $1"
    exit 1
}

# Environment detection
detect_environment() {
    if [ -z "$APP_ENV" ]; then
        export APP_ENV="dev"
    fi
    log_message "Detected environment: $APP_ENV"
}

# Wait for service availability
wait_for_service() {
    local service_host=$1
    local service_port=$2
    local service_name=$3
    local max_retries=${4:-30}

    log_message "Waiting for $service_name to be ready..."
    for ((attempt=1; attempt<=max_retries; attempt++)); do
        if nc -z -w5 "$service_host" "$service_port"; then
            log_message "$service_name is ready!"
            return 0
        fi
        log_message "Waiting for $service_name... attempt $attempt/$max_retries"
        sleep 2
    done

    log_message "Error: $service_name did not become ready in time"
    return 1
}

# Function to wait for MySQL
wait_for_mysql() {
    log_message "Waiting for MySQL to be ready..."
    local retries=30
    local count=0
    
    while [ $count -lt $retries ]; do
        if mysqladmin ping -h"$DATABASE_HOST" -u"$DATABASE_USER" -p"$DATABASE_PASSWORD" --silent; then
            log_message "MySQL is ready!"
            return 0
        fi
        count=$((count + 1))
        log_message "Waiting for MySQL... attempt $count/$retries"
        sleep 2
    done
    
    log_message "Error: MySQL did not become ready in time"
    return 1
}

# Function to setup database
# setup_database() {
#     log_message "Checking if database exists..."
#     if ! mysql -h"$DATABASE_HOST" -u"$DATABASE_USER" -p"$DATABASE_PASSWORD" -e "USE $DATABASE_NAME" 2>/dev/null; then
#         log_message "Database does not exist. Creating database..."
#         mysql -h"$DATABASE_HOST" -u"$DATABASE_USER" -p"$DATABASE_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS $DATABASE_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
#         log_message "Database created successfully!"
#     else
#         log_message "Database already exists."
#     fi
# }

# Database setup
setup_database() {
    if [ "$APP_ENV" != "test" ]; then
        wait_for_service "$DATABASE_HOST" 3306 "MySQL"
        
        # Database creation and migration logic
        php bin/console doctrine:database:create --if-not-exists --env="$APP_ENV"
        php bin/console doctrine:migrations:migrate --no-interaction --env="$APP_ENV"
    fi
}

# RabbitMQ setup
setup_rabbitmq() {
    wait_for_service "$RABBITMQ_HOST" 5672 "RabbitMQ"
    
    # Install Stomp library for RabbitMQ
    pecl install stomp
    docker-php-ext-enable stomp
}

# Symfony cache management
manage_symfony_cache() {
    log_message "Managing Symfony cache for $APP_ENV environment"
    
    case "$APP_ENV" in
        prod)
            php bin/console cache:clear --env=prod --no-debug
            php bin/console cache:warmup --env=prod --no-debug
            ;;
        dev)
            php bin/console cache:clear --env=dev
            php bin/console cache:warmup --env=dev
            ;;
    esac
}

# Permissions setup
setup_permissions() {
    chown -R www-data:www-data var/
    chmod -R 755 var/
}

# Function to setup Symfony
setup_symfony() {
    log_message "Setting up Symfony..."
    
    # Clear and warmup cache
    log_message "Clearing and warming up cache..."
    php bin/console cache:clear --env=prod --no-debug
    php bin/console cache:warmup --env=prod --no-debug
    
    # Run migrations
    log_message "Running database migrations..."
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
    
    # Set proper permissions
    log_message "Setting file permissions..."
    chown -R www-data:www-data var/
    chmod -R 755 var/
}

# Function to setup cron
setup_cron() {
    log_message "Setting up cron job..."
    
    # Export environment variables for cron
    printenv | grep -v "no_proxy" > /etc/environment
    
    # Ensure cron log file exists and has proper permissions
    touch /var/log/cron.log
    chmod 0644 /var/log/cron.log
    
    # Start cron service
    service cron start
    log_message "Cron service started"
}

cleanup_supervisor() {
    log_message "Cleaning up supervisor files..."
    supervisor_files="/var/run/supervisord.sock /var/run/supervisord.pid /var/log/supervisor/supervisord.log"
    
    for file in $supervisor_files; do
        rm -f "$file"
    done
    
    touch /var/log/supervisor/supervisord.log
    chmod 777 /var/log/supervisor/supervisord.log || error_exit "Failed to set permissions on supervisord.log"
}

setup_logs() {
    log_message "Setting up log files..."
    
    log_files="/var/log/news_consumer.out.log /var/log/news_consumer.err.log"
    
    for file in $log_files; do
        if ! touch "$file"; then
            error_exit "Failed to create log file: $file"
        fi
        if ! chmod 666 "$file"; then
            error_exit "Failed to set permissions on log file: $file"
        fi
    done
}

# Main execution
main() {
    log_message "Starting container initialization..."

    cleanup_supervisor
    detect_environment
    setup_database
    setup_rabbitmq
    manage_symfony_cache
    setup_permissions
    setup_logs

    # Start services based on environment
    # supervisord -c /etc/supervisor/supervisord.conf
    exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
    
    # Wait for MySQL
    # wait_for_mysql
    
    # # Setup database
    # setup_database
    
    # # Setup Symfony
    # setup_symfony
    
    # # Setup cron
    # setup_cron
    
    log_message "Container initialization completed"
    
    # Start Apache in foreground
    # log_message "Starting Apache..."
    # exec apache2-foreground
}

# Execute main function
main "$@"