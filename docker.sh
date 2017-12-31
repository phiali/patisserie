#!/usr/bin/env bash

phinx() {
    docker exec patisserie_patisserie_1 /bin/sh -c "
        cd /var/www/ &&
        vendor/robmorgan/phinx/bin/phinx $*
    "
}

composer() {
    docker exec patisserie_patisserie_1 /bin/sh -c "
        cd /var/www/ &&
        /usr/local/bin/composer $*
    "
}

info() {
    echo -e "\nPlease read docker/readme.txt file first\n"
    echo "Usage:"
    echo "  composer - run Composer within the container"
    echo "  phinx    - run Phinx within the container"
}

if [[ $1 == "" ]]; then
    info
else
    case "$1" in
       "composer") shift; composer $@
       ;;
       "phinx") shift; phinx $@
       ;;
    esac
fi
