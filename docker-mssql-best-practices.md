# Docker and Microsoft SQL Server Architecture & Best Practices

## Overview

This document outlines the container architecture, configuration patterns, and operational best practices for running Microsoft SQL Server in Docker within the Police Analytics platform ecosystem. It establishes guidelines for process signaling, container health probing, multi-database consolidation, initialization sidecars, and local development security.

## 1. Direct PID 1 Signal Handling & Graceful Shutdown

### The Problem

The official Microsoft SQL Server container image (`mcr.microsoft.com/mssql/server:2022-latest`) defaults to using `/opt/mssql/bin/launch_sqlservr.sh` as its PID 1 entrypoint process. This bash wrapper script does not forward POSIX `SIGTERM` signals down to the underlying `sqlservr` binary.

When `docker compose down` or `docker stop` is invoked:

1. Docker delivers `SIGTERM` to PID 1 (the shell script), which ignores it.
2. Docker waits for the container stop timeout (default 10s), concludes the process is hung, and issues `SIGKILL`.
3. `sqlservr` is abruptly terminated mid-execution. Dirty memory pages in the buffer pool are not flushed to disk, uncommitted transactions are interrupted without rollbacks, and no clean checkpoint is recorded.
4. On the subsequent container boot, SQL Server is forced into an expensive crash recovery (parallel redo) phase, causing extended boot times and potential database state corruption on persistent volumes.

### The Best Practice

Bypass the default launcher script by specifying `sqlservr` directly as the container's entrypoint, and grant an explicit 30-second stop grace period:

```yaml
services:
  database-mssql:
    image: mcr.microsoft.com/mssql/server:2022-latest
    entrypoint: ["/opt/mssql/bin/sqlservr"]
    command: []
    stop_grace_period: 30s
```

By pointing the entrypoint directly to `/opt/mssql/bin/sqlservr`, the database daemon receives `SIGTERM` directly from Docker, flushes dirty pages, completes an orderly shutdown checkpoint, and terminates cleanly in 1–2 seconds.

## 2. Recovery-Aware, Dual-Cadence Healthchecks (`Msg 904`)

### The Problem

During container boot, Microsoft SQL Server initializes system databases (`master`, `model`, `msdb`, `tempdb`) first. As soon as `master` is online, the network listener opens on port 1433 and SQL Server accepts incoming TCP connections.

However, user databases stored on persistent volumes are recovered asynchronously in background worker threads (executing redo/undo transactions and writing recovery checkpoints).

If a container healthcheck naively runs a basic query:

```yaml
# Anti-pattern: premature healthy signal
test: ["CMD", "/opt/mssql-tools18/bin/sqlcmd", "-S", "localhost", "-U", "sa", "-P", "Password123", "-C", "-Q", "SELECT 1"]
```

1. `SELECT 1` queries `master` by default and succeeds immediately (~5s into container boot).
2. Docker marks the container status as `healthy`.
3. Dependent initialization sidecars or application containers immediately launch and issue `USE [Authorization];`.
4. Because the user database is still completing background recovery, SQL Server rejects the query:

```text
Msg 904, Level 16, State 3, Line 1:
Database 'Authorization' cannot be autostarted during server shutdown or startup.
```

### The Best Practice

Query the `sys.databases` catalog directly to assert that all required user databases are online, multi-user, and writable. Combine this with error aborts (`-b`) and dual-rate polling:

```yaml
healthcheck:
  test: [
    "CMD", "/opt/mssql-tools18/bin/sqlcmd",
    "-S", "localhost", "-U", "sa", "-P", "Password123",
    "-C", "-b", "-l", "2", "-t", "2",
    "-Q", "IF EXISTS (SELECT 1 FROM sys.databases WHERE name IN (N'Authorization', N'Authorization_test', N'NicheRMSReport') AND (state_desc <> N'ONLINE' OR user_access_desc <> N'MULTI_USER' OR is_read_only = 1)) THROW 50001, 'Required database is not ready for initialization', 1; SELECT 1;"
  ]
  start_period: 60s
  start_interval: 1s
  interval: 10s
  timeout: 10s
  retries: 15
```

- **Recovery Gate:** Throws custom error `50001` if any required database is recovering, in single-user mode, or read-only.
- **Dual-Rate Polling:**
  - `start_interval: 1s`: Probes aggressively every second during cold engine startup so developers do not wait an extra 10 seconds once the engine is ready.
  - `interval: 10s`: Automatically downshifts to quiet 10-second checks once healthy, preventing CPU churn and TLS handshake overhead.
  - `-l 2 -t 2`: Enforces 2-second login and query timeouts so health checks never hang on network socket deadlocks.
  - `-C`: Trusts the self-signed server certificate (mandatory in tools 18+).

## 3. Database Topology & Memory Consolidation

### The Problem

Running multiple SQL Server containers locally (such as separate containers for application data and external mock replica data) multiplies container overhead. Each `sqlservr` engine allocates its own buffer pool, worker thread scheduler, query compilation cache, and duplicate sets of system databases, consuming 1.5 GB to 2.5 GB of RAM per container.

### The Best Practice

Consolidate multiple logical databases into a single SQL Server engine container:

```text
[database-mssql Container: ~1.8 GB Total RAM]
 ├── System: master, tempdb, model, msdb
 ├── User DB 1: [Authorization] (Primary Dev)
 ├── User DB 2: [Authorization_test] (In-Container PHPUnit)
 └── User DB 3: [NicheRMSReport] (Read-Only Simulated Police RMS)
```

- **Shared Engine Resources:** All databases share a single buffer pool, connection manager, and query execution engine.
- **Zero Idle Overhead:** Idle databases (such as `Authorization_test` during frontend browser work) consume 0% CPU and only negligible disk space (~16 MB for data and log files).
- **Process Isolation:** SQL Server natively enforces transaction boundaries, schema locks, and catalog isolation across databases on the same instance.
- **Host Resource Savings:** Reduces developer workstation Docker memory footprint by ~2 GB or more.

## 4. Initializer Sidecars & Deprecated Image Replacement

### The Problem

The legacy `mcr.microsoft.com/mssql-tools:latest` container image has been deprecated by Microsoft. It uses outdated v17 utilities with OpenSSL 1.1 dependencies and requires downloading an unnecessary ~500 MB secondary image.

Furthermore, launching application containers before database migrations complete causes early HTTP requests to fail against missing tables.

### The Best Practice

1. **Reuse the Engine Image for Initializers:** The `mcr.microsoft.com/mssql/server:2022-latest` image already bundles modern tools at `/opt/mssql-tools18/bin/sqlcmd`. Using it as an ephemeral sidecar avoids pulling separate tool images:

```yaml
db-initializer:
  image: mcr.microsoft.com/mssql/server:2022-latest
  container_name: authz-db-init
  environment:
    MSSQL_SA_PASSWORD: "Password123"
    DB_HOST: "database-mssql"
  volumes:
    - ../../database/sql:/schema:ro
    - ./init-db.sh:/init-db.sh:ro
  entrypoint: ["/bin/bash", "/init-db.sh"]
  depends_on:
    database-mssql:
      condition: service_healthy

api:
  depends_on:
    db-initializer:
      condition: service_completed_successfully
```

2. **Bounded Polling in Initialization Scripts:** `init-db.sh` must execute a bounded retry loop (up to 30 retries with 2-second backoff) verifying `sys.databases` readiness before attempting DDL execution.
3. **Strict Exit Codes:** Execute scripts with `set -eo pipefail` and `-b` so any SQL error immediately terminates initialization with a non-zero exit code, blocking dependent application services from starting against broken schemas.

## 5. Loopback Port Binding & Network Isolation

### The Problem

Binding container ports to `0.0.0.0` (such as `"1433:1433"`) exposes the SQL Server listener on all host network interfaces. This leaves developer databases accessible to other devices on the same local subnet or public Wi-Fi network.

### The Best Practice

Bind explicitly to the loopback interface:

```yaml
ports:
  - "127.0.0.1:1433:1433"
```

This restricts database traffic strictly to the local machine while allowing local tools (such as Azure Data Studio, SSMS, or JetBrains DataGrip) full access.

## 6. T-SQL Script Discipline & Idempotency

### 1. Bracket Reserved Keywords

Keywords such as `Authorization`, `Order`, `Group`, and `User` are reserved T-SQL keywords. Failing to bracket them in DDL statements produces syntax errors:

```sql
-- Incorrect (fails with Msg 156):
IF DB_ID('Authorization') IS NOT NULL DROP DATABASE Authorization;

-- Correct:
IF DB_ID('Authorization') IS NOT NULL DROP DATABASE [Authorization];
```

### 2. Explicit Database Context Headers

Do not rely solely on external `-d <database>` CLI flags. Ensure every schema and seed script declares its target database context:

```sql
USE [Authorization];
GO
```

### 3. Single Authoritative Schema Location

Never duplicate canonical DDL files across multiple folders (e.g. keeping one in `database/sql/` and a duplicate in `dev/docker/sql/`). Mount the canonical application schema directly from the repository's single source of truth into `/schema:ro` to prevent schema drift.

## 7. Storage Volumes & Reset Strategies

### 1. Named Persistent Volumes

Mount SQL Server data files at `/var/opt/mssql` using an explicitly named Docker volume:

```yaml
volumes:
  authz-mssql-data:
    name: authz-mssql-data
```

### 2. Fast In-Place Resets vs. Destructive Cold Boots

- **Destructive Reset (`docker compose down -v`):** Deletes the volume and forces a 30–45s cold boot to re-create system databases and restore volume metadata.
- **In-Place Reset (~2 seconds):** Drops and recreates the target user databases or wipes mutable tables in foreign-key dependency order, followed by re-seeding against the warm, running engine.

## 8. Architectural Decision Matrix & Environmental Tradeoffs

Different applications and team workflows justify different architectural choices. The following decision matrix details the four core dimensions of containerized Microsoft SQL Server environments, the conditions each approach is optimized for, and the operational triggers that warrant moving between them.

### Dimension 1: Container Topology (Single-Engine Multi-DB vs. Dual Dedicated Stacks)

| Approach | Architecture | Optimized For | Trade-offs & Limitations |
| :--- | :--- | :--- | :--- |
| **Approach 1A: Single-Engine, Multi-DB** *(authz-admin standard)* | One SQL Server container hosting `[App]`, `[App_test]`, and `[MockReplica]` on port `1433` | • Local development workstations with standard RAM allocations (consumes only ~1.5–2.0 GB total).<br>• Zero cold-boot latency when running test suites against an already-warm instance.<br>• Monorepos where test runners execute inside container networks.<br>• Clean framework-level database catalog routing (e.g. `APP_ENV=test`). | • Unsuitable if automated test suites execute destructive volume tear-downs (`down -v`).<br>• Potential lock contention if a developer actively clicks through the UI while running heavy batch tests simultaneously. |
| **Approach 1B: Dual Dedicated Stacks** *(Warrants pattern)* | Dev stack on port `1433` (`warrants-mssql`) + separate test daemon on port `14333` (`warrants-test-mssql`) | • Complete physical firewall isolation between developer UI data and automated test suites.<br>• Environments where test scripts execute raw DML table truncations.<br>• Host-native test runners connecting over distinct TCP ports.<br>• Multi-developer shared remote Docker environments. | • Heavy memory footprint (**~3.5 GB to 5.0 GB+ RAM** running two independent SQL Server engines).<br>• Port management overhead and potential collisions on port 14333.<br>• Cold-boot delay if the test daemon is not running. |

#### Migration Triggers:

- **Move from 1A to 1B when:** Test suites execute destructive schema wipes or high-concurrency batch syncs that lock tables or destroy manual UI session state needed for active exploratory testing.
- **Move from 1B to 1A when:** Developer machines experience RAM starvation, or test execution moves into containers where framework routing cleanly handles database isolation.

### Dimension 2: Test Execution Seam (Container-Bound vs. Host-Native)

| Approach | Architecture | Optimized For | Trade-offs & Limitations |
| :--- | :--- | :--- | :--- |
| **Approach 2A: Container-Bound Harness** *(authz-admin standard & Warrants Delivery 96)* | Tests execute inside the Linux API container via `docker compose exec` | • **Zero developer host prerequisites:** Developers do not need native Microsoft ODBC Driver 18 (`msodbcsql18`), `unixodbc`, or PHP extensions installed on macOS or Windows.<br>• 100% runtime parity with production Linux containers. | • Minor invocation overhead (~200ms) to launch `docker exec` processes compared to native host binaries. |
| **Approach 2B: Host-Native Execution** *(Warrants Delivery 62 legacy)* | Tests execute natively on host OS against published Docker loopback ports (`127.0.0.1:14333`) | • Direct IDE integration (running individual tests via native "Play" buttons in VS Code, PHPStorm, or GoLand).<br>• Sub-millisecond process invocation speed. | • High developer friction: requires installing, configuring, and troubleshooting OS-specific ODBC drivers and extensions natively across macOS, Windows, and Linux. |

#### Migration Triggers:

- **Move from 2B to 2A when:** Onboarding new team members or AI agents across differing host operating systems where native ODBC driver installation creates recurring setup friction.
- **Move from 2A to 2B when:** Sub-second IDE test runners or profiling tools require direct in-process debugging from the host operating system.

### Dimension 3: Test State Reset Mechanism (Dynamic ORM vs. In-Place DML vs. Transactional)

| Approach | Reset Strategy | Optimized For | Trade-offs & Limitations |
| :--- | :--- | :--- | :--- |
| **Approach 3A: Dynamic ORM Schema Tool** *(authz-admin standard)* | Doctrine ORM / DBAL dynamically drops and rebuilds tables in `[Authorization_test]` from PHP entity attributes | • Projects driven by an ORM (Doctrine, Entity Framework, Prisma, Hibernate).<br>• Zero manual SQL reset scripts to maintain when entity properties change. | • Slower resets than raw DML (~3–5s vs ~2s) because it executes DDL table drops and creates. |
| **Approach 3B: In-Place Raw DML Wipe & Reseed** *(Warrants pattern)* | Executes raw T-SQL scripts (`DELETE FROM ...` in FK dependency order + reseed) | • High-performance test suites requiring ~2-second full database resets.<br>• Systems leveraging raw T-SQL features (views, triggers, `OPENJSON`, procs) that ORMs cannot model. | • High maintenance: every schema change requires updating both DDL and wipe scripts in strict dependency order. |
| **Approach 3C: Transactional Rollback** | Each test executes inside an outer database transaction (`BEGIN TRAN` ... `ROLLBACK`) | • Fast unit and controller integration tests requiring sub-millisecond resets (~5ms per test). | • Cannot test code that commits internal nested transactions, tests background workers, or executes multi-connection lock evaluation. |

#### Migration Triggers:

- **Move from 3A to 3B when:** Test suites grow large enough that DDL schema recreation overhead becomes a bottleneck, or when raw database triggers and procedures are introduced.
- **Move from 3B/3A to 3C when:** Running hundreds of read-heavy unit tests where transaction rollback provides instant test iteration.

### Dimension 4: Startup Coordination (Compose Dependencies vs. In-App Retries)

| Approach | Coordination Strategy | Optimized For | Trade-offs & Limitations |
| :--- | :--- | :--- | :--- |
| **Approach 4A: Compose Dependency Gate** *(Platform Standard)* | `api` declares `depends_on: { db-initializer: { condition: service_completed_successfully } }` | • Local Docker Compose development and CI runners.<br>• Guarantees application containers never boot until database recovery is complete and schemas are seeded. | • Exclusive to Docker Compose; does not translate directly to raw cloud deployments (Kubernetes, AWS ECS). |
| **Approach 4B: In-App Readiness Polling** | Application entrypoint loops on `SELECT 1` with exponential backoff on container boot | • Distributed cloud deployments (Kubernetes pods, AWS ECS tasks, Azure App Service) where container engines do not share Compose healthcheck primitives. | • Application logs get cluttered with transient connection error traces during boot. |

#### Migration Triggers:

- **Move from 4A to 4B when:** Packaging application containers for production deployment targets (Kubernetes, ECS, IIS) where external container orchestrators manage container boot lifecycles independently.
