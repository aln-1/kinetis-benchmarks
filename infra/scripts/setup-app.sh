#!/bin/sh
set -e

if ! command -v git >/dev/null 2>&1; then
    sudo apt-get update -y
    sudo apt-get install -y git
fi

if ! command -v docker >/dev/null 2>&1; then
    curl -fsSL https://get.docker.com | sudo sh
    sudo usermod -aG docker ubuntu
fi

if [ -d /home/ubuntu/kinetis-benchmarks ]; then
    (cd /home/ubuntu/kinetis-benchmarks && sudo -u ubuntu git pull)
else
    sudo -u ubuntu git clone https://github.com/aln-1/kinetis-benchmarks.git /home/ubuntu/kinetis-benchmarks
fi

cd /home/ubuntu/kinetis-benchmarks

sudo docker build -t kb-kinetis -f kinetis/docker/Dockerfile ./kinetis > /tmp/build-kinetis.log 2>&1 &
sudo docker build -t kb-kinetis-fpm -f kinetis-fpm/Dockerfile . > /tmp/build-kinetis-fpm.log 2>&1 &
sudo docker build -t kb-laravel-octane -f laravel-octane/Dockerfile . > /tmp/build-laravel-octane.log 2>&1 &
for name in laravel symfony codeigniter yii2 cakephp slim slim-frankenphp; do
    sudo docker build -t "kb-${name}" "./${name}" > "/tmp/build-${name}.log" 2>&1 &
done
wait

echo "Build results:"
for name in kinetis kinetis-fpm laravel-octane laravel symfony codeigniter yii2 cakephp slim slim-frankenphp; do
    if sudo docker image inspect "kb-${name}" >/dev/null 2>&1; then
        echo "  ${name}: OK"
    else
        echo "  ${name}: FAILED — see /tmp/build-${name}.log"
    fi
done
