---
name: e2e-testing
description: End-to-end testing with Playwright, including test organization, Page Object Model, configuration, CI/CD integration, and handling unreliable tests.
origin: ECC
---

# E2E Testing with Playwright

## Overview
This skill encompasses comprehensive Playwright patterns for constructing dependable, efficient, and sustainable end-to-end test suites. The content covers test organization, the Page Object Model, configuration strategies, CI/CD workflows, artifact handling, and approaches for managing unreliable tests.

## Core Competencies

**Test Architecture**
- Organizing test files by feature domain (auth, features, api)
- Implementing Page Object Model to encapsulate UI interactions
- Structuring tests with setup/teardown hooks and descriptive naming

**Page Object Model Implementation**
- Creating reusable page classes that abstract locators and actions
- Encapsulating navigation, input, and assertion logic
- Implementing helper methods like "search(query: string)" that handle both UI and network expectations

**Configuration & Environment Setup**
- Configuring Playwright with device coverage (Chromium, Firefox, WebKit, mobile)
- Setting appropriate timeouts and wait strategies
- Integrating web servers and managing test environments

**Flakiness Diagnosis & Resolution**
- Identifying root causes: race conditions, network timing, animation delays
- Employing deterministic waits ("await page.waitForResponse(...)") instead of arbitrary timeouts
- Using test repeatability commands to expose intermittent failures

**Artifact Management**
- Capturing screenshots, traces, and videos strategically
- Configuring retention policies based on test outcomes
- Organizing artifacts for post-test analysis

**CI/CD Integration**
- GitHub Actions workflows with artifact uploads
- Parallel execution optimization and retry strategies
- Environment variable management for staging/production separation

**Specialized Domains**
- Web3/wallet mocking with injected providers
- Financial transaction testing with appropriate environment guards
- Long-running operations with extended timeout handling

## Key Patterns

Use data-testid attributes for reliable element selection. Implement "waitFor" strategies that respond to actual application state rather than arbitrary delays. Quarantine flaky tests temporarily while investigating root causes.
