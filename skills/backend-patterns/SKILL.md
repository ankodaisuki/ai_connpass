---
name: backend-patterns
description: Backend development patterns for API design, database optimization, caching, error handling, authentication, rate limiting, background jobs, and logging.
origin: ECC
---

# Backend Development Patterns

## Overview
This resource covers server-side architecture for Node.js, Express, and Next.js applications, including REST/GraphQL design, database optimization, caching, error handling, and authentication.

## Key Pattern Areas

**API Design**: Resource-based URLs with standard HTTP verbs, query parameters for filtering/pagination, and separation of concerns through repository and service layers.

**Database Optimization**: The guide emphasizes "Select only needed columns" rather than wildcard queries, and addresses the N+1 problem through batch fetching instead of sequential queries within loops.

**Caching Strategies**: Redis-backed cache-aside patterns with TTL management and cache invalidation methods for frequently accessed data like market records.

**Error Handling**: Centralized error handlers that distinguish between operational errors (with specific status codes) and unexpected failures, with retry logic using exponential backoff.

**Authentication**: JWT token validation with role-based access control (RBAC) patterns that define permissions per user role (admin, moderator, user).

**Rate Limiting**: Emphasizes shared stores like Redis rather than in-memory counters, which fail in multi-instance or serverless environments.

**Background Jobs**: Simple queue pattern for asynchronous processing without blocking API responses.

**Logging**: Structured JSON logging with request IDs, context, and error details for observability.

## When to Use
Activate these patterns when designing APIs, implementing layered architectures, optimizing queries, adding middleware, or building authentication/caching systems.
