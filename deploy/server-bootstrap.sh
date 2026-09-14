#!/usr/bin/env bash
# Run ONCE on a fresh Oracle Cloud "Oracle Linux 9" VM (as the `opc` user, via ssh oracle-vm).
# Installs Docker + Compose plugin + Node.js, opens the firewall for 80/443, clones the repo.
set -euo pipefail

REPO_URL="${1:?Usage: server-bootstrap.sh <git-repo-url>}"

echo "==> Installing Docker"
sudo dnf install -y dnf-utils
sudo dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
# Oracle Linux 9 reports as $releasever=9 which the CentOS repo doesn't have packages for yet;
# some OL9 images need this override to resolve docker-ce packages correctly.
sudo sed -i 's/$releasever/9/g' /etc/yum.repos.d/docker-ce.repo
sudo dnf install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo systemctl enable --now docker
sudo usermod -aG docker "$USER"

echo "==> Opening firewall (firewalld) for HTTP/HTTPS"
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload

echo "==> Installing Node.js 22 (to build the frontend on the server)"
curl -fsSL https://rpm.nodesource.com/setup_22.x | sudo -E bash -
sudo dnf install -y nodejs git

echo "==> Cloning repo"
if [ ! -d app ]; then
  git clone "$REPO_URL" app
fi

cat <<'EOF'

==> Done. Log out and back in (or run `newgrp docker`) so the docker group applies.
Next steps:
  cd app
  ./deploy/setup-env.sh <SERVER_PUBLIC_IP>
  ./deploy/deploy.sh
EOF
