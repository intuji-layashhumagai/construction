#!/bin/bash

# Enable strict error handling
set -e

# Check if the .env file exists
APP_ENV_FILE="./html/.env"
if [[ ! -f "${APP_ENV_FILE}" ]]; then
  echo "Error: The environment file not found at ${APP_ENV_FILE}"
  exit 1
fi

if [[ ! -f "./html/certs/ca.cert.pem" ]]; then
  mkdir -p ./html/certs
  echo "Generating self-signed CA certificate..."
  openssl req -x509 -newkey rsa:2048 -keyout ./html/certs/ca.key.pem -out ./html/certs/ca.cert.pem -days 3650 -subj "/C=NP/ST=Bagmati/L=Kathmandu/O=Intuji/CN=layash/emailAddress=layash.humagai@intuji.com" -nodes
fi

# Set current user IDs in the environment
USER_ID=$(id -u)
GROUP_ID=$(id -g)

export USER_ID GROUP_ID

# Docker Compose command wrapper
COMPOSE_CMD="docker compose --env-file $APP_ENV_FILE --file compose.yml"

# Create the network for the services
docker network create csm-network >/dev/null 2>&1 || true

# Function to stop and prune Docker containers
stop_docker() {
  echo "Stopping Docker containers..."
  ${COMPOSE_CMD} down --remove-orphans

  echo "Removing dangling Docker resources..."
  docker container prune -f
  docker network prune -f
  docker builder prune -f
  docker image prune -f
  docker volume prune -f
}

start_docker() {
  # Start services
  echo "Starting Docker containers..."
  ${COMPOSE_CMD} up --build
}

# Check if the argument is "stop"
if [[ "$1" == "stop" ]]; then
  # Stop services
  stop_docker
else
  start_docker
fi
