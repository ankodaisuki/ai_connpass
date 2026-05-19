---
name: docker-patterns
description: Docker and Docker Compose best practices for containerized development, multi-container orchestration, networking, volumes, and security hardening.
origin: ECC
---

# Docker Patterns

## Overview

Docker Patterns provides best practices for containerized development using Docker and Docker Compose. This skill covers local development workflows, multi-container orchestration, networking, volumes, and security hardening.

## Key Concepts

**When to Use**: Setting up Docker Compose for development, designing multi-container systems, troubleshooting networking issues, or migrating to containerized workflows.

### Development Environment Setup

The pattern recommends a "Standard Web App Stack" combining application, database, cache, and email testing services. The approach uses a bind mount for source code to enable hot reloading while protecting container dependencies with an anonymous volume for `node_modules`.

### Multi-Stage Dockerfile Pattern

The guide emphasizes separating concerns across Docker build stages: dependencies, development (with debugging tools), build (with optimizations), and production (minimal footprint). This approach ensures the final production image contains only essential components.

### Service Configuration

Development and production configurations diverge through override files. The documentation notes: "Development (auto-loads override)" applies debug settings automatically, while production requires explicit file composition with resource limits and restart policies.

## Networking & Volumes

**Service Discovery**: Services resolve by name within the Compose network (e.g., "db" container accessible as `db:5432`).

**Volume Types**: Named volumes persist across restarts, bind mounts enable live code updates, and anonymous volumes protect container-generated content from host interference.

## Security Practices

Key hardening steps include:

- Using specific image tags instead of `:latest` for reproducibility
- Running containers as non-root users
- Dropping unnecessary Linux capabilities
- Managing secrets via `.env` files (never committed) or Docker Secrets
- Implementing read-only filesystems where feasible

The guide explicitly warns: "BAD: Hardcoded in image" — secrets should never be built into layers.

## Debugging & Troubleshooting

Essential commands include log inspection (`docker compose logs`), interactive access (`docker compose exec`), and network diagnostics (DNS resolution, connectivity checks via `wget`).
