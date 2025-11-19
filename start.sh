#!/bin/bash

# Enable strict error handling
set -e

# Check if the .env file exists
APP_ENV_FILE="./html/.env"
if [[ ! -f "${APP_ENV_FILE}" ]]; then
  echo "Error: The environment file not found at ${APP_ENV_FILE}"
  exit 1
fi

# Set current user IDs in the environment
USER_ID=$(id -u)
GROUP_ID=$(id -g)
export USER_ID GROUP_ID

# Docker Compose command wrapper
COMPOSE_CMD="docker compose --env-file $APP_ENV_FILE --file compose.yml"

# Create the network for the services
docker network create csm-network >/dev/null 2>&1 || true

echo "Starting Docker containers..."
  ${COMPOSE_CMD} up --build