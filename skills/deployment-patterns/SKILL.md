---
name: deployment-patterns
description: Production deployment workflows, CI/CD best practices, containerization, deployment strategies, health checks, and production readiness guidelines.
origin: ECC
---

# Deployment Patterns

## Overview
This document covers production deployment workflows and CI/CD best practices, including containerization, deployment strategies, health checks, and production readiness guidelines.

## Key Deployment Strategies

**Rolling Deployment** gradually replaces instances with zero downtime but requires backward-compatible changes. "Old and new versions run simultaneously during rollout."

**Blue-Green Deployment** maintains two identical environments for instant rollback capability. "Run two identical environments. Switch traffic atomically." This approach doubles infrastructure costs temporarily.

**Canary Deployment** routes initial traffic to new versions cautiously. "Route a small percentage of traffic to the new version first," enabling real-world validation before full rollout.

## Docker Containerization

The guide provides multi-stage Dockerfile examples for Node.js, Go, and Python applications. Key practices include:
- Using specific version tags (avoiding `:latest`)
- Running as non-root users for security
- Implementing HEALTHCHECK instructions
- Optimizing layer caching by copying dependencies first

## CI/CD Pipeline Structure

A standard GitHub Actions pipeline includes:
- Test stage (linting, type-checking, unit/integration tests)
- Build stage (Docker image creation)
- Deploy stage (environment-specific deployment)

The document emphasizes "Validate at startup — fail fast if config is wrong" for environment configuration using schema validation.

## Health Checks & Monitoring

Endpoints should return meaningful status information. Kubernetes probes (liveness, readiness, startup) ensure proper orchestration. Configuration should follow the Twelve-Factor App pattern, using environment variables exclusively.

## Production Readiness

The comprehensive checklist covers application quality, infrastructure setup, monitoring, security, and operational procedures—including documented rollback strategies and runbooks for failure scenarios.
