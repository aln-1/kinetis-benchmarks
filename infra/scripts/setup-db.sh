#!/bin/sh
set -e

if ! command -v git >/dev/null 2>&1 || ! command -v mysqld >/dev/null 2>&1; then
    sudo apt-get update -y
    sudo apt-get install -y git mysql-server
fi

if [ -d /home/ubuntu/kinetis-benchmarks ]; then
    (cd /home/ubuntu/kinetis-benchmarks && sudo -u ubuntu git pull)
else
    sudo -u ubuntu git clone https://github.com/aln-1/kinetis-benchmarks.git /home/ubuntu/kinetis-benchmarks
fi

sudo sed -i 's/^bind-address.*/bind-address = 0.0.0.0/' /etc/mysql/mysql.conf.d/mysqld.cnf
sudo sed -i 's/^mysqlx-bind-address.*/mysqlx-bind-address = 0.0.0.0/' /etc/mysql/mysql.conf.d/mysqld.cnf
sudo sed -i '/^max_connections/d' /etc/mysql/mysql.conf.d/mysqld.cnf
echo "max_connections = 1000" | sudo tee -a /etc/mysql/mysql.conf.d/mysqld.cnf >/dev/null
sudo sed -i '/^max_connect_errors/d' /etc/mysql/mysql.conf.d/mysqld.cnf
echo "max_connect_errors = 1000000" | sudo tee -a /etc/mysql/mysql.conf.d/mysqld.cnf >/dev/null
sudo sed -i '/^innodb_flush_log_at_trx_commit/d' /etc/mysql/mysql.conf.d/mysqld.cnf
{
    echo "skip-log-bin"
    echo "innodb_flush_log_at_trx_commit = 2"
} | sudo tee -a /etc/mysql/mysql.conf.d/mysqld.cnf >/dev/null
sudo systemctl restart mysql
sudo rm -f /var/lib/mysql/binlog.*
sudo systemctl enable mysql

sudo mysql -e "CREATE DATABASE IF NOT EXISTS tfbench;"
sudo mysql -e "CREATE USER IF NOT EXISTS 'tfbench'@'%' IDENTIFIED BY 'tfbench';"
sudo mysql -e "GRANT ALL PRIVILEGES ON tfbench.* TO 'tfbench'@'%';"
sudo mysql -e "FLUSH PRIVILEGES;"

sudo mysql --default-character-set=utf8mb4 tfbench < /home/ubuntu/kinetis-benchmarks/schema/seed.sql

echo "DB setup complete. World rows: $(mysql -N -e 'SELECT COUNT(*) FROM tfbench.world;')"
echo "Fortune rows: $(mysql -N -e 'SELECT COUNT(*) FROM tfbench.fortune;')"
