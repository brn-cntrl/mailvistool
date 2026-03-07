FROM php:8.2-apache-bookworm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    libc-client-dev \
    libkrb5-dev \
    unzip \
    git \
    && rm -r /var/lib/apt/lists/*

# Configure and install PHP extensions
RUN docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install imap pdo_sqlite

# Enable Apache rewrite module
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Install dependencies
RUN composer install --no-interaction --optimize-autoloader --ignore-platform-reqs

# Create database directory and set permissions
RUN mkdir -p database && chmod 777 database

# Expose port 80
EXPOSE 80
