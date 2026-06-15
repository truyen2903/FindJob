#!/bin/bash
set -e

apt-get update -y
apt-get install -y ca-certificates curl gnupg git nginx apache2-utils certbot python3-certbot-nginx

install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc

echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu noble stable" > /etc/apt/sources.list.d/docker.list

apt-get update -y
apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

usermod -aG docker ubuntu

systemctl enable docker
systemctl start docker

mkdir -p /var/www

rm -rf "${staging_app_dir}" "${production_app_dir}"

git clone -b develop "${repo_url}" "${staging_app_dir}"
git clone -b main "${repo_url}" "${production_app_dir}"

chown -R ubuntu:ubuntu "${staging_app_dir}" "${production_app_dir}"

cat > /etc/nginx/.htpasswd-staging <<'AUTH'
${staging_basic_auth_line}
AUTH

cat > /etc/nginx/sites-available/jobfind <<'NGINX'
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    return 444;
}

server {
    listen 80;
    listen [::]:80;
    server_name ${domain_name} www.${domain_name};

    client_max_body_size 50M;

    location / {
        proxy_pass http://127.0.0.1:8082;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}

server {
    listen 80;
    listen [::]:80;
    server_name ${staging_domain_name};

    auth_basic "Staging Area";
    auth_basic_user_file /etc/nginx/.htpasswd-staging;

    client_max_body_size 50M;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
NGINX

rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/jobfind /etc/nginx/sites-enabled/jobfind

nginx -t
systemctl enable nginx
systemctl restart nginx
