---
name: api-design
description: REST API design patterns, conventions for resource naming, HTTP semantics, status codes, pagination, filtering, error handling, authentication, rate limiting, and versioning.
origin: ECC
---

# API Design Patterns

## Overview
This resource provides comprehensive guidance on designing consistent, developer-friendly REST APIs with conventions for resource naming, HTTP semantics, status codes, pagination, filtering, error handling, authentication, rate limiting, and versioning.

## Key Principles

**Resource Design**: "Resources are nouns, plural, lowercase, kebab-case" like `/api/v1/users` rather than `/api/v1/getUsers` or singular forms.

**HTTP Status Codes**: Use semantically appropriate codes—201 Created for successful resource creation with Location header, 404 Not Found for missing resources, 422 Unprocessable Entity for validation failures, and 429 Too Many Requests for rate limit exceeded scenarios.

**Response Format**: Wrap responses in a consistent envelope with `data`, optional `meta` for pagination, and `links` for navigation. Error responses include structured error codes and field-level details.

## Pagination Strategies

**Offset-Based**: Simple implementation supporting "jump to page N" but slow on large offsets (100,000+).

**Cursor-Based**: Superior performance regardless of position, stable with concurrent inserts, but cannot jump to arbitrary pages.

The resource recommends cursor-based pagination for public APIs and large datasets, offset for small datasets and search results.

## Additional Coverage

The guide addresses filtering via query parameters, sorting with `-` prefix for descending, sparse fieldsets, token-based authentication patterns, role-based authorization, rate limit tiers, versioning strategies with deprecation timelines, and implementation examples in TypeScript/Next.js, Python/Django, and Go.

Includes practical API design checklist covering naming conventions, HTTP methods, validation, error handling, authentication, authorization, rate limiting, and documentation requirements.
