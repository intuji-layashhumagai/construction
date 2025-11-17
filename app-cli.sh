#!/bin/bash

# Check if the .env file exists
APP_ENV_FILE="./html/.env"
if [[ ! -f "${APP_ENV_FILE}" ]]; then
  echo "Error: .env file not found. Exiting..."
  exit 1
fi

# Set current user IDs in the environment
USER_ID=$(id -u)
GROUP_ID=$(id -g)
export USER_ID GROUP_ID

# Login to the app container
docker compose --env-file $APP_ENV_FILE --file compose.yml exec --user "${USER_ID}" csm-api bash
