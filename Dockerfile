FROM node:24.22.2-bookworm-slim@sha256:c86ecead9e55a5210ac1727de38a4ca840bf03dcaf34dc9749addbf383b1920c AS node

FROM php:8.4-cli-bookworm@sha256:54b467ede1db96c66675e7b78457dd43729d0e7be31978d4a1e5c069ceabcf76

COPY --from=composer:2.10.3@sha256:2cf275428757e5435a667bfc0d038b14520ca02be2b1b70d44a05c8c19e2de48 /usr/bin/composer /usr/bin/composer
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules/npm /usr/local/lib/node_modules/npm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev libzip-dev unzip \
    && docker-php-ext-install pcntl pdo_mysql pdo_sqlite zip \
    && ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
    && php -v \
    && composer --version \
    && node --version \
    && npm --version

WORKDIR /var/www/html

EXPOSE 8000
