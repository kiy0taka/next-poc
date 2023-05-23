#!/bin/bash

while getopts e:g: OPT
do
    case $OPT in
        e) ECCUBE_APP_ENV=$OPTARG ;;
        g) CODECEPTION_GROUP=$OPTARG ;;
    esac
done

# trap 'echo "trap SIGINT"' SIGINT

ECCUBE_APP_ENV=${ECCUBE_APP_ENV:-codeception}
CODECEPTION_ARGS="-g ${CODECEPTION_GROUP}"

docker compose -f docker-compose.yml -f docker-compose.pgsql.yml -f docker-compose.e2e.yml down
# docker volume rm -f ec-cube_pg-database ec-cube_var

if [ -f .env ]; then
    mv .env .env.bak
fi

docker compose \
    -f docker-compose.yml \
    -f docker-compose.pgsql.yml \
    -f docker-compose.e2e.yml \
    up -d --build --wait

docker compose exec --user=www-data ec-cube \
    bash -c 'echo "APP_ENV='${ECCUBE_APP_ENV}'" > .env'

if [[ ${CODECEPTION_GROUP} != restrict-file-upload ]]; then
    CODECEPTION_ARGS="${CODECEPTION_ARGS} --skip-group restrict-file-upload"
else
    docker compose exec --user=www-data ec-cube \
        bash -c 'echo "ECCUBE_RESTRICT_FILE_UPLOAD=1" > .env'
fi

docker compose exec --user=www-data ec-cube \
    bash -c 'for d in $(ls codeception/_data/plugins | grep 1.0.0); do (cd codeception/_data/plugins/$d; tar zcf ../../../../repos/${d}.tgz *); done'

docker compose exec --user=www-data ec-cube \
    vendor/bin/codecept \
    -vvv run acceptance \
    --env chrome,docker \
    --html report.html \
    ${CODECEPTION_ARGS}
