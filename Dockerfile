ARG TAG=8.1-apache-bullseye
FROM php:${TAG} as base

ENV APACHE_DOCUMENT_ROOT /var/www/html

RUN apt update \
  && apt upgrade -y \
  && apt install --no-install-recommends -y \
    apt-transport-https \
    apt-utils \
    build-essential \
    curl \
    debconf-utils \
    gcc \
    git \
    vim \
    gnupg2 \
    libfreetype6-dev \
    libicu-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libpq-dev \
    libzip-dev \
    locales \
    ssl-cert \
    unzip \
    zlib1g-dev \
    libwebp-dev \
  && apt upgrade -y ca-certificates \
  && apt clean \
  && rm -rf /var/lib/apt/lists/* \
  && echo "en_US.UTF-8 UTF-8" >/etc/locale.gen \
  && locale-gen \
  ;

RUN docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
  && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
  && docker-php-ext-install -j$(nproc) zip gd mysqli pdo_mysql opcache intl pgsql pdo_pgsql \
  ;

RUN pecl install apcu && echo "extension=apcu.so" > /usr/local/etc/php/conf.d/apc.ini

RUN curl -sL https://deb.nodesource.com/setup_12.x | bash - \
  && apt update \
  && apt install -y nodejs \
  && apt clean \
  ;

RUN mkdir -p ${APACHE_DOCUMENT_ROOT} \
  && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
  && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
  ;

RUN a2enmod rewrite headers ssl
# Enable SSL
RUN ln -s /etc/apache2/sites-available/default-ssl.conf /etc/apache2/sites-enabled/default-ssl.conf
# see https://stackoverflow.com/questions/73294020/docker-couldnt-create-the-mpm-accept-mutex/73303983#73303983
RUN echo "Mutex posixsem" >> /etc/apache2/apache2.conf
EXPOSE 443

# Use the default production configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
# Override with custom configuration settings
COPY dockerbuild/php.ini $PHP_INI_DIR/conf.d/
COPY dockerbuild/docker-php-entrypoint /usr/local/bin/

RUN curl -sS https://getcomposer.org/installer \
  | php \
  && mv composer.phar /usr/bin/composer

RUN composer config -g repos.packagist composer https://packagist.jp

HEALTHCHECK --interval=10s --timeout=5s --retries=30 CMD pgrep apache

FROM base as develop

RUN git config --global --add safe.directory ${APACHE_DOCUMENT_ROOT}

# Setup JetBrains Gateway
ARG IDEURL=https://download.jetbrains.com/idea/ideaIU-2023.1.3.tar.gz
ENV REMOTE_DEV_JDK_DETECTION=true
RUN cd /opt && \
  curl -fsSL -o ide.tar.gz $IDEURL && \
  mkdir ide && \
  tar xfz ide.tar.gz --strip-components=1 -C ide && \
  rm ide.tar.gz

RUN /opt/ide/bin/remote-dev-server.sh installPlugins ${APACHE_DOCUMENT_ROOT} \
  com.jetbrains.php \
  com.jetbrains.twig \
  de.espend.idea.php.annotation \
  fr.adrienbrault.idea.symfony2plugin


# Setup Xdebug
ENV COMPOSER_ALLOW_XDEBUG=1
ENV XDEBUG_SESSION=eccube
RUN pecl install xdebug && \
  docker-php-ext-enable xdebug && \
  { \
    echo 'xdebug.mode=debug'; \
    echo 'xdebug.remote_enable=true'; \
    echo 'xdebug.remote_host=localhost'; \
    echo 'xdebug.remote_port=9003'; \
  } >> ${PHP_INI_DIR}/conf.d/docker-php-ext-xdebug.ini


FROM base as test

COPY . ${APACHE_DOCUMENT_ROOT}
WORKDIR ${APACHE_DOCUMENT_ROOT}

RUN find ${APACHE_DOCUMENT_ROOT} \( -path ${APACHE_DOCUMENT_ROOT}/vendor -prune \) -or -print0 \
  | xargs -0 chown www-data:www-data \
  && find ${APACHE_DOCUMENT_ROOT} \( -path ${APACHE_DOCUMENT_ROOT}/vendor -prune \) -or \( -type d -print0 \) \
  | xargs -0 chmod g+s \
  ;

