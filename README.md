# Construction Management System (CSM)

## Overview

This project implements a construction management system with offline-first capabilities, event sourcing architecture, and cloud synchronization. It supports distributed devices with conflict resolution and real-time data synchronization.

## Key Features

- **Event Sourcing**: Immutable event store with vector clock causality tracking
- **Vector Clocks**: Distributed event ordering without relying on device timestamps
- **Conflict Resolution**: Automatic conflict detection and resolution for concurrent events
- **Materialized Views**: Computed current state from event replay
- **Queue-Based Processing**: Async job processing with Redis queues and automatic cleanup
- **Certificate Authentication**: Secure device authentication with emergency access
- **Session Management**: Comprehensive sync session tracking and status updates
- **Clock Recovery**: Handles device replacement scenarios with vector clock reconstruction
- **Deterministic Ordering**: Ensures consistent event ordering across distributed devices

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
- **Testing**: Playwright for E2E tests

## Prerequisites

- Docker and Docker Compose
- Git

## Setup and Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd construction
   ```

2. **Start the services**
   ```bash
   ./start.sh
   ```
   This automatically clears stale jobs and starts the queue worker.
3. **CLI Access**

   To access the Laravel application container for running commands:

   ```bash
   ./app-cli.sh
   ```

4. **Access the application**
   - API: http://localhost:8080
   - PostgreSQL: localhost:5432
   - Redis: localhost:6379
   - Redis Insight: http://localhost:5540

## Services

- **csm-api**: Laravel application server with Nginx and PHP-FPM
- **csm-postgres**: PostgreSQL database for event store and materialized views
- **csm-redis**: Redis for caching, queues, and session management
- **csm-redis-insight**: Web UI for Redis monitoring and management


## Development

The application uses volume mounting for live code reloading during development. Composer dependencies are installed during the Docker build process.

## Project Structure

- `html/`: Laravel application source code
- `scripts/`: Database initialization and utility scripts
- `compose.yml`: Docker Compose configuration
- `Dockerfile`: Custom Docker image for the Laravel app
- `entrypoint.sh`: Container initialization with queue management


