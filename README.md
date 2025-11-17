# Construction Management System (CSM)

## Overview

This project implements a construction management system with offline-first capabilities, event sourcing architecture, and cloud synchronization. It supports distributed devices with conflict resolution and real-time data synchronization.

## Architecture

The system is designed with the following components:

- **Offline Devices**: Mobile/web apps with local caching and sync controllers  
- **Event System**: Append-only event store using vector clocks  
- **Application Layer**: Sync API, authentication, business rules, reporting service  
- **Data Layer**: PostgreSQL for events and materialized views, Redis for caching and queues  


## Tech Stack

- **Backend**: Laravel (PHP 8.4)
- **Database**: PostgreSQL 17.4
- **Cache/Queue**: Redis 8.0
- **Containerization**: Docker & Docker Compose
- **Frontend**: Laravel with Livewire and Flux UI

## Prerequisites

- Docker and Docker Compose
- Git

## Setup and Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd construction/system
   ```

2. **Start the services**
   ```bash
   docker-compose up --build
   ```

3. **Access the application**
   - API: http://localhost:8080
   - PostgreSQL: localhost:5432
   - Redis: localhost:6379

## Services

- **csm-api**: Laravel application server with Nginx and PHP-FPM
- **csm-postgres**: PostgreSQL database for event store and materialized views
- **csm-redis**: Redis for caching, queues, and session management

## Development

The application uses volume mounting for live code reloading during development. Composer dependencies are installed during the Docker build process.

## Project Structure

- `html/`: Laravel application source code
- `scripts/`: Database initialization and utility scripts
- `compose.yml`: Docker Compose configuration
- `Dockerfile`: Custom Docker image for the Laravel app


