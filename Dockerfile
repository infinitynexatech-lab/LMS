# Infinity Learn — Moodle 4.5 LTS image, built from upstream sources.
#
# Why custom: Bitnami removed bitnami/moodle from public Docker Hub in 2025
# (their "Secure Images" paywall). Building from php:8.3-apache + upstream
# Moodle git is the cleanest config-overlay analog to Corteza's official image.

FROM php:8.3-apache-bookworm

ARG MOODLE_BRANCH=MOODLE_405_STABLE
ENV MOODLE_DOCROOT=/var/www/html
ENV MOODLE_DATAROOT=/var/www/moodledata

# Build deps (-dev) and runtime deps.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip wget cron ghostscript graphviz aspell \
        libxml2-dev libcurl4-openssl-dev libonig-dev libzip-dev \
        libpng-dev libjpeg-dev libfreetype6-dev libicu-dev \
        libldap2-dev libxslt-dev libpq-dev libpspell-dev libsodium-dev \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions required + recommended by Moodle 4.5.
RUN docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        gd intl mysqli pdo_mysql zip xsl soap opcache \
        bcmath exif pcntl sodium ldap pspell xml \
    && pecl install redis \
    && docker-php-ext-enable redis opcache

# PHP ini tuned for Moodle.
RUN { \
        echo 'max_input_vars = 5000'; \
        echo 'memory_limit = 512M'; \
        echo 'post_max_size = 256M'; \
        echo 'upload_max_filesize = 256M'; \
        echo 'max_execution_time = 300'; \
        echo 'expose_php = Off'; \
        echo 'opcache.enable = 1'; \
        echo 'opcache.memory_consumption = 256'; \
        echo 'opcache.max_accelerated_files = 12000'; \
        echo 'opcache.revalidate_freq = 60'; \
    } > /usr/local/etc/php/conf.d/moodle.ini

# Pull Moodle source (4.5 LTS, security-patched). chdir out of /var/www/html
# first — it's the base image's WORKDIR, and rm -rf'ing it from inside breaks
# git's "Unable to read current working directory".
WORKDIR /tmp
RUN rm -rf ${MOODLE_DOCROOT} \
    && git clone --depth=1 --branch ${MOODLE_BRANCH} \
        https://github.com/moodle/moodle.git ${MOODLE_DOCROOT} \
    && mkdir -p ${MOODLE_DATAROOT} \
    && chown -R www-data:www-data ${MOODLE_DOCROOT} ${MOODLE_DATAROOT}
WORKDIR ${MOODLE_DOCROOT}

# Apache modules + vhost.
RUN a2enmod rewrite headers expires
COPY docker/apache-moodle.conf /etc/apache2/sites-available/000-default.conf

# Overlay our theme + local plugin (mounted at runtime in dev; baked here for
# clean prod builds — bind-mounts still win at runtime if present).
COPY theme/infinity ${MOODLE_DOCROOT}/theme/infinity
COPY local/infinity ${MOODLE_DOCROOT}/local/infinity

# Entry: run admin/cli/install.php on first boot if config.php missing.
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
