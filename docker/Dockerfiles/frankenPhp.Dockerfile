FROM dunglas/frankenphp:php8.4

ENV PHP_VERSION=8.4 \
    USER=magento

# System packages
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    tzdata \
    patch \
    unzip \
    jq \
    jpegoptim \
    optipng \
    pngquant \
    make \
    findutils \
    which && \
    rm -rf /var/lib/apt/lists/*

# PHP extensions (install-php-extensions ships with FrankenPHP)
RUN install-php-extensions \
    bcmath \
    gd \
    intl \
    mbstring \
    pdo_mysql \
    soap \
    sockets \
    sodium \
    xsl \
    zip \
    opcache \
    xdebug

# Installing composer
RUN curl -sS https://getcomposer.org/download/2.8.4/composer.phar --output /usr/local/bin/composer && \
    chmod +x /usr/local/bin/composer && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# Configuring permissions
RUN useradd -s /bin/bash -m $USER && \
    mkdir -p /home/$USER/.composer && \
    chown -R $USER:$USER /home/$USER && \
    mkdir -p /var/www/vhosts/$PHP_VERSION && \
    chown -R $USER:$USER /var/www/vhosts

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s \
    CMD curl -fsS http://127.0.0.1:8080/health_check.php || exit 1

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]