FROM            debian:8

# Install basic software for server
RUN             apt-get update && \
                apt-get upgrade -y && \
                apt-get install -y \
                    curl \
                    git \
                    vim \
                    wget \
                    apt-transport-https \
                    lsb-release \
                    ca-certificates && \
                wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg && \
                echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list

# Fix vim controls
RUN             echo "set term=builtin_ansi" >> ~/.vimrc

# Install PHP
RUN             apt-get update && \
                apt-get install -y \
                    php7.2-cli \
                    php7.2-curl \
                    php7.2-mbstring \
                    php7.2-xml \
                    php7.2-zip

# Clean apt-get
RUN             apt-get clean && \
                rm -rf /var/lib/apt/lists/*

# Override PHP setup
RUN             sed -i "s/;date.timezone =.*/date.timezone = UTC/g" /etc/php/7.2/cli/php.ini && \
                sed -i "s/;error_log =.*/error_log = \\/var\\/docker_stderr/g" /etc/php/7.2/cli/php.ini && \
                mkdir -p /var/www

# Copy files
WORKDIR         /var/www
COPY            . /var/www

# Install Composer
RUN             php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
                php composer-setup.php && \
                php -r "unlink('composer-setup.php');" && \
                mv composer.phar /usr/local/bin/composer

# Install the Compose components
RUN             composer install

# Run server
CMD             ["php", "/var/www/bin/server.php"]
