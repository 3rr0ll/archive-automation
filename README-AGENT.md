# Archive Automation

A Laravel application for automating website recovery and deployment workflows using Blade, Alpine.js, queued jobs, and integrations such as RunCloud, Cloudflare, the Internet Archive, and Google Search Console.

This README defines the project structure, coding conventions, naming rules, and implementation practices. Follow these conventions consistently when adding or modifying code.

## 1. Technology Stack

- **Backend:** Laravel, PHP, Eloquent ORM
- **Frontend:** Laravel Blade, Alpine.js, Tailwind CSS
- **Build tooling:** Vite, npm
- **Database:** MySQL
- **Background processing:** Laravel Queues and Jobs, Redis where configured
- **Integrations:** RunCloud API, Cloudflare API, Internet Archive, Google Search Console

Use the versions installed in `composer.json` and `package.json` as the source of truth.

## 2. Architecture

Use a **modular monolith**. Keep related code grouped by feature while retaining Laravel's familiar conventions.

The application is a hybrid server-rendered and API-driven Blade application:

1. Laravel returns Blade views for page loads and navigation.
2. Alpine.js manages local interaction and page state.
3. JavaScript services call Laravel JSON endpoints using `fetch`.
4. Controllers validate requests, authorize access, and coordinate application work.
5. Services contain reusable business logic and integration operations.
6. Jobs run long-running or retryable operations in the background.
7. Eloquent models represent and persist application data.

Do not move all business logic into Blade templates, Alpine components, route closures, or controllers.

### Request flow

```text
Browser
  ├── GET page ──> web.php ──> Web Controller ──> Blade View
  └── AJAX/Fetch ─> api.php ──> API Controller
                                  ├── Form Request validation
                                  ├── Authorization
                                  ├── Service
                                  │    ├── Eloquent Model
                                  │    ├── External API Client
                                  │    └── Dispatch Job
                                  └── JSON Resource / JSON response
```

## 3. Project Structure

Prefer the following structure where it fits the feature. Do not create folders or abstraction layers without a concrete need.

```text
app/
├── Actions/                 # Single-purpose application actions, when useful
├── Enums/                   # PHP enums
├── Http/
│   ├── Controllers/
│   │   ├── Api/             # JSON API controllers
│   │   └── Web/             # Controllers returning views/redirects
│   ├── Middleware/
│   ├── Requests/            # Form Request validation
│   └── Resources/           # API response resources
├── Jobs/                    # Queueable background jobs
├── Models/                  # Eloquent models
├── Services/
│   ├── Cloudflare/
│   ├── GoogleSearchConsole/
│   ├── InternetArchive/
│   └── RunCloud/
├── Support/                 # Shared helpers with clear, limited responsibility
└── Providers/

resources/
├── css/
├── js/
│   ├── components/          # Alpine component definitions
│   ├── services/            # API clients grouped by feature
│   └── utils/               # Small reusable frontend utilities
└── views/
    ├── components/          # Reusable Blade components
    ├── layouts/
    ├── pages/
    └── partials/

routes/
├── web.php                  # Browser pages and session-based routes
└── api.php                  # JSON API routes

database/
├── factories/
├── migrations/
└── seeders/

tests/
├── Feature/
└── Unit/
```

Feature-specific subfolders may be added under controllers, services, jobs, requests, and JavaScript services as the project grows. Keep naming and organization predictable.

## 4. Naming Conventions

### PHP and Laravel

| Item | Convention | Example |
|---|---|---|
| Classes | `StudlyCase` | `ArchiveDeploymentService` |
| Methods and functions | `camelCase` | `createWebApp()` |
| Variables | `camelCase` | `$domainName` |
| Constants | `UPPER_SNAKE_CASE` | `DEFAULT_TIMEOUT` |
| Models | Singular `StudlyCase` | `Deployment`, `Domain` |
| Controllers | Resource/feature + `Controller` | `DeploymentController` |
| API controllers | Feature + `Controller` under `Api` | `Api\DeploymentController` |
| Form Requests | Action + subject + `Request` | `StoreDeploymentRequest` |
| API Resources | Subject + `Resource` | `DeploymentResource` |
| Jobs | Action + subject | `DeployArchivedWebsite` |
| Services | Subject + `Service` | `CloudflareDnsService` |
| Interfaces | Subject + `Interface` | `ArchiveProviderInterface` |
| Enums | Singular concept, `StudlyCase` | `DeploymentStatus` |
| Database tables | Plural `snake_case` | `deployments`, `domain_records` |
| Database columns | `snake_case` | `started_at`, `domain_name` |
| Foreign keys | Singular model + `_id` | `deployment_id` |
| Route names | Dot-separated `snake_case` or Laravel resource convention | `deployments.index` |
| Config keys | `snake_case` | `services.runcloud.base_url` |

Additional PHP rules:

- Follow PSR-12 and Laravel's established style.
- Use explicit return types for methods and functions whenever practical.
- Use constructor property promotion when it improves clarity.
- Use `declare(strict_types=1);` consistently if adopted for the project; do not introduce it selectively without agreement.
- Prefer typed properties and parameters. Avoid `mixed` and never use `any`-style untyped data as a shortcut.
- Use enums for fixed sets of statuses or types when they improve correctness.
- Keep classes focused. A class should have one clear responsibility.
- Use descriptive names. Avoid abbreviations such as `$cfg`, `$tmp`, or `$resp` unless the meaning is obvious in a very small scope.
- Do not use vague names such as `Manager`, `Helper`, or `Processor` when a more specific responsibility can be named.

### JavaScript and Alpine.js

| Item | Convention | Example |
|---|---|---|
| Files | `kebab-case.js` | `deployment-service.js` |
| Variables | `camelCase` | `deploymentId` |
| Functions | `camelCase` | `fetchDeployments()` |
| Alpine component names | `camelCase` | `deploymentTable` |
| Alpine methods | `camelCase` | `loadDeployments()` |
| Constants | `UPPER_SNAKE_CASE` | `API_BASE_URL` |
| CSS classes | Tailwind utilities; custom classes in `kebab-case` | `deployment-row` |
| DOM IDs | `kebab-case` | `deployment-status` |
| Data attributes | `kebab-case` | `data-deployment-id` |

JavaScript rules:

- Use ES modules and `import`/`export`.
- Prefer `const`; use `let` only when reassignment is required. Avoid `var`.
- Use `async`/`await` for asynchronous work.
- Handle loading, empty, success, and error states explicitly.
- Keep API calls in feature service modules rather than repeating `fetch` logic throughout Blade templates.
- Keep Alpine components focused on UI state and interaction. They should call services instead of containing endpoint URLs and request construction everywhere.
- Avoid global mutable state unless there is a documented application-wide need.
- Do not add a frontend framework for a small interaction that Alpine.js already handles.

### Blade

- Use `kebab-case` for Blade component filenames, for example `deployment-card.blade.php`.
- Use descriptive component names, for example `<x-deployment-card />`.
- Use `@props` to define component inputs.
- Keep business logic and complex data transformation out of Blade.
- Use Blade for rendering, layout composition, and simple presentation conditions.
- Escape output with `{{ }}` by default. Use `{!! !!}` only for explicitly trusted, sanitized HTML.
- Keep page views under `resources/views/pages/` and shared layout elements under `layouts/`, `components/`, or `partials/` according to their purpose.

## 5. Routes and API Design

### Route files

- `routes/web.php` is for page routes, browser navigation, and session-based web flows.
- `routes/api.php` is for JSON endpoints consumed by JavaScript or external API clients.
- Keep route definitions declarative. Do not place business logic in route closures.
- Group routes by middleware, authentication, and feature where useful.
- Give named routes to browser pages and use consistent route naming.
- Use HTTP methods according to the operation: `GET` to read, `POST` to create or trigger, `PATCH`/`PUT` to update, and `DELETE` to remove.

Example:

```php
use App\Http\Controllers\Api\DeploymentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/deployments', [DeploymentController::class, 'index']);
    Route::post('/deployments', [DeploymentController::class, 'store']);
    Route::get('/deployments/{deployment}', [DeploymentController::class, 'show']);
    Route::delete('/deployments/{deployment}', [DeploymentController::class, 'destroy']);
});
```

Adapt authentication middleware to the authentication mechanism actually configured in the application. Do not add Sanctum or another package unless it is installed and required.

### Endpoint conventions

- Use plural resource names, such as `/api/deployments`.
- Use route parameters for resource identifiers, such as `/api/deployments/{deployment}`.
- Keep URLs lowercase and use hyphens only when a multiword path is needed.
- Return predictable JSON structures and appropriate HTTP status codes.
- Use API Resources for structured responses when they provide useful consistency.
- Do not expose secrets, internal stack traces, filesystem paths, or raw provider credentials in responses.
- Keep pagination, filtering, and sorting parameters consistent across list endpoints.

Example response:

```json
{
  "data": {
    "id": 42,
    "domain": "example.com",
    "status": "queued"
  },
  "message": "Deployment queued."
}
```

Use Laravel's normal JSON response conventions where suitable; do not force every endpoint into a custom envelope if the project already uses a consistent alternative.

## 6. Controllers, Requests, Services, and Models

### Controllers

Controllers are the HTTP boundary. They should:

- Receive the request.
- Validate input through a Form Request for non-trivial validation.
- Authorize the operation.
- Call the relevant service or dispatch a job.
- Return a view, redirect, or JSON response.

Controllers should not contain lengthy integration logic, multi-step deployment procedures, or large database workflows.

### Form Requests

- Use Form Requests for validation that is reused or more than a few simple rules.
- Name them by action and subject, such as `StoreDomainRequest`.
- Keep validation rules and request-specific authorization in the request class where appropriate.
- Validate all user input on the server, even if the frontend already validates it.

### Services

Services contain reusable business logic and external integration work.

- Name services for the system or capability they own, such as `RunCloudWebAppService`.
- Keep each service cohesive and avoid a single service that controls the entire application.
- Inject dependencies through constructors.
- Keep provider-specific HTTP requests inside the corresponding integration service or client.
- Return clear values or throw documented exceptions; avoid hidden side effects.
- Do not make services depend on controllers or Blade views.

### Models

- Use Eloquent relationships rather than manually repeating joins where practical.
- Define casts, relationships, scopes, and persistence-related behavior on the model.
- Keep models focused on the data and behavior of that entity.
- Avoid putting external API orchestration or long deployment workflows in models.
- Use mass-assignment protection (`$fillable` or `$guarded`) deliberately.

## 7. Frontend API Services and Alpine.js

Use a JavaScript service for each feature's API operations. Components should call those service methods rather than constructing requests repeatedly.

Example service:

```js
// resources/js/services/deployment-service.js

const BASE_URL = '/api/deployments';

export async function fetchDeployments() {
    const response = await fetch(BASE_URL, {
        headers: {
            Accept: 'application/json',
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error('Unable to load deployments.');
    }

    return response.json();
}

export async function createDeployment(payload) {
    const response = await fetch(BASE_URL, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content') ?? '',
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
    });

    if (!response.ok) {
        throw new Error('Unable to create deployment.');
    }

    return response.json();
}
```

Example Alpine component:

```js
// resources/js/components/deployment-table.js

import { fetchDeployments } from '../services/deployment-service.js';

export function deploymentTable() {
    return {
        deployments: [],
        isLoading: false,
        errorMessage: '',

        async loadDeployments() {
            this.isLoading = true;
            this.errorMessage = '';

            try {
                const result = await fetchDeployments();
                this.deployments = result.data ?? [];
            } catch (error) {
                this.errorMessage = error.message || 'Something went wrong.';
            } finally {
                this.isLoading = false;
            }
        },
    };
}
```

Register Alpine components in the project's JavaScript entry point and initialize Alpine according to the installed setup. Avoid placing large JavaScript expressions directly in Blade attributes when a named component is clearer.

For session-authenticated API calls, configure CSRF protection correctly for the application's Laravel setup. Do not disable CSRF protection to make a request work.

## 8. Background Jobs and Workflow Design

Use queued jobs for operations that may take significant time, need retries, or involve external systems. Examples include archive retrieval, file transfer, extraction, deployment verification, DNS checks, and indexing requests.

- Name jobs as clear actions, such as `DownloadArchiveSnapshot`.
- Keep jobs small enough to understand and retry.
- Make jobs idempotent where possible so retries do not duplicate side effects.
- Set explicit timeouts, retry limits, and backoff behavior based on the operation.
- Persist each domain's workflow state and step results.
- Record useful progress and error information for the UI.
- Do not rely on a browser request remaining open while a long workflow runs.
- Isolate failures by domain and workflow step. One failed domain should not silently corrupt or cancel unrelated work.
- Apply controlled concurrency and respect provider API limits and server resources.
- Do not assume DNS nameserver migration or propagation waiting is required unless the configured workflow explicitly calls for it.

## 9. Database and Migrations

- Use descriptive, plural `snake_case` table names.
- Use Laravel migration naming conventions, such as `create_deployments_table`.
- Make schema changes through migrations; do not edit production schema manually.
- Add indexes for frequently queried columns and foreign keys where appropriate.
- Use foreign key constraints when consistent with the application's deletion and retention rules.
- Store timestamps in database-supported timestamp/datetime columns and use Laravel's timezone configuration consistently.
- Use transactions for related database writes that must succeed or fail together.
- Do not hold database transactions open while making slow external API calls.
- Avoid storing credentials or API tokens in plaintext application tables.
- Use factories and seeders for repeatable development and test data.

## 10. External Integrations

Keep each provider's implementation isolated in its own service area.

- Read credentials and configuration from environment-backed Laravel config, not directly from scattered `env()` calls.
- Set request timeouts and handle connection failures.
- Check provider response status and validate required response fields.
- Handle rate limits and transient errors with appropriate retry behavior.
- Log provider name, operation, relevant resource ID, and safe error details.
- Never log access tokens, secret keys, authorization headers, or sensitive user data.
- Keep provider response formats from leaking throughout the application. Normalize them into application-level values where useful.
- Use mocked HTTP responses in tests; do not require real provider credentials for ordinary automated tests.

## 11. Error Handling, Logging, and Statuses

- Use Laravel exceptions and validation responses consistently.
- Return actionable, user-safe error messages to the frontend.
- Keep technical diagnostics in server logs, not in public JSON responses.
- Include a correlation identifier or workflow identifier in logs when it helps trace a deployment.
- Use structured, consistent workflow statuses. Define allowed statuses centrally, preferably with a PHP enum when appropriate.
- Record status transitions and timestamps where the history is important.
- Distinguish a failed operation from a pending, queued, retrying, or cancelled operation.
- Do not mark a workflow complete until its required verification steps have passed.

## 12. Security

- Enforce authentication and authorization on every protected route.
- Validate and authorize all input on the server.
- Protect state-changing browser requests with CSRF protection.
- Escape rendered user-provided content.
- Keep secrets in environment variables and deployment configuration.
- Restrict file uploads by size, type, destination, and access permissions.
- Prevent path traversal when handling archive extraction and file operations.
- Do not allow user input to become an unrestricted shell command or filesystem path.
- Use least-privilege provider tokens and server credentials.
- Avoid exposing internal IDs or provider data beyond what the user needs.
- Never commit `.env`, credentials, private keys, or production data.

## 13. Testing

Use PHPUnit, following the project's configured Laravel test setup.

- **Unit tests:** test isolated service logic, status transitions, and data transformations.
- **Feature tests:** test routes, validation, authorization, JSON responses, and database behavior.
- **Integration tests:** mock external HTTP services and test request/response handling.
- **Job tests:** verify dispatching, retry-safe behavior, and state updates.
- Test success, validation failure, unauthorized access, provider failure, timeout, and retry scenarios.
- Use descriptive test names that state the expected behavior.
- Avoid tests that depend on production services, real domains, or real credentials.

Run the relevant test suite before considering a change complete:

```bash
php artisan test
```

## 14. Code Quality Rules

- Prefer simple, readable code over clever abstractions.
- Avoid duplicated logic, but do not create abstractions for one-off code without a clear benefit.
- Keep functions and methods short and focused.
- Use early returns to reduce deeply nested conditionals.
- Avoid hidden global state and unnecessary static methods.
- Do not suppress exceptions without handling or documenting the reason.
- Avoid broad `catch (Throwable)` blocks unless the boundary requires a final safety net and the error is logged and handled.
- Do not introduce dependencies without explaining the need.
- Do not add placeholder methods, dead code, unused imports, or commented-out old implementations.
- Preserve existing conventions when modifying established code unless there is a specific reason to change them.
- Do not use `any` in JavaScript or leave PHP values untyped merely to bypass type errors.
- Keep comments focused on why a non-obvious decision exists, not on restating the code.

## 15. Environment and Commands

Copy the example environment file and configure local values:

```bash
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run dev
```

Common commands:

```bash
# Start Laravel locally
php artisan serve

# Run Vite
npm run dev

# Build frontend assets
npm run build

# Run database migrations
php artisan migrate

# Run tests
php artisan test

# Start a queue worker
php artisan queue:work
```

On Windows, use the equivalent copy command supported by the active shell. Configure the queue connection and run the required worker process for the chosen environment.

Never commit real environment values. Keep `.env.example` updated with variable names and safe placeholder values only.

## 16. Git and Change Management

- Use short, descriptive branch names, for example `feat/deployment-api`, `fix/archive-extraction`, or `docs/coding-guidelines`.
- Use imperative, specific commit messages, for example `Add deployment status endpoint`.
- Keep changes focused and avoid mixing unrelated refactors with feature work.
- Review the diff for secrets, debug statements, generated files, and accidental changes before committing.
- Update tests and documentation when behavior or setup changes.
- Do not rewrite shared branch history unless the team explicitly agrees.

## 17. AI Coding Agent Instructions

When using an AI coding assistant on this repository:

1. Inspect the existing files and conventions before making changes.
2. Identify the feature boundary and the expected request-to-response or job workflow.
3. Reuse existing services, components, models, and utilities when suitable.
4. Follow the naming and folder conventions in this README.
5. Make the smallest complete change that solves the requested task.
6. Do not replace working architecture or add packages without a clear reason.
7. Validate input, enforce authorization, and handle failure paths.
8. Add or update tests for changed behavior.
9. Run relevant tests, formatters, or build commands when available.
10. Summarize files changed, behavior added, commands run, and any remaining risks.

Do not invent requirements, credentials, API behavior, or successful integration results. If a provider's behavior or a project requirement is unclear, inspect existing code and project documentation first, then ask for clarification when needed.

## 18. Definition of Done

A change is ready when:

- Naming and folder conventions are followed.
- Validation and authorization are implemented where required.
- Business logic is in the appropriate service or job, not embedded in the view.
- Loading, success, empty, and error states are handled in the UI when applicable.
- External operations have timeouts and safe error handling.
- Database changes are represented by migrations.
- Tests are added or updated and relevant tests pass.
- No secrets, debug artifacts, or unrelated changes are included.
- Documentation is updated where needed.
