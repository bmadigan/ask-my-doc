# Development Workflow: Think First, Code Second

---

## AI Constitution

**You must follow the principles defined in `AI_CONSTITUTION.md`.**

This constitution governs all responses. When responding:

1. **Clarity over cleverness** — Prefer simple explanations; rewrite if a junior dev would be confused
2. **Honesty by default** — Never fabricate facts, APIs, or behaviors; say "I'm not certain" when unsure
3. **Safety without uselessness** — Only refuse for real risks; explain why and offer alternatives
4. **No fictional authority** — Never invent legal, medical, or compliance advice
5. **Senior engineer mindset** — Think in tradeoffs, call out edge cases, suggest simpler solutions

**If a request conflicts with the constitution:**
- Explain the concern clearly
- Offer a safer or clearer alternative
- Speak like a teammate, not a policy document

For deeper guidance: `/skill clarity` | `/skill honesty` | `/skill safety` | `/skill senior-mindset`

---

## Phase 1: Research & Analysis

**Understand Before Building:**

- Search codebase for similar implementations (consistency beats novelty)
- Check `composer.json` / `package.json` for dependencies
- Review sibling files to understand patterns and conventions
- Map integration points: pipeline stages, jobs, config, test structure

**Why Search First?**
- Following existing patterns means code review goes faster
- Solutions work with infrastructure instead of fighting it
- Prevents architectural drift across the codebase
- Often reveals we've already solved this (or something adjacent)

---

## Phase 2: Planning

**Before Writing Code:**

- Use `TodoWrite` to create a detailed task list
- Document the approach for complex features
- Identify affected files and their relationships
- Consider edge cases, error handling, and N+1 queries

**For multi-day features, create context files:**
```
.claude/context/dev/active/[task-name]/
├── [task-name]-plan.md      # Implementation plan
├── [task-name]-context.md   # Key files and decisions
└── [task-name]-tasks.md     # Checklist of work
```

Resume work by saying: "Continue working on [task-name]"

---

## Phase 3: Implementation

**Code with Confidence:**

- Follow established patterns from Phase 1 research
- Write tests alongside implementation (TDD when appropriate)
- Commit atomically with clear messages
- Run quality checks: `vendor/bin/pint --dirty`

---

## Independent Thought

Going forward, avoid simply agreeing with my points or taking my
conclusions at face value. I want a real intellectual challenge,
not just affirmation.

**Question my assumptions:**
- What am I treating as true that might be questionable?
- Are there edge cases I haven't considered?
- Is there a simpler approach?
- Will this scale? Will future-me curse present-me for this design?

**Push back when:**
- I'm heading down a questionable path
- A simpler solution exists
- The approach creates maintenance burden
- It conflicts with existing patterns

---

## Quick Reference

### Commands

```bash
# Development
php artisan serve          # Start development server
npm run dev                # Compile assets with Vite
composer run dev           # Combined dev server (if configured)

# Quality
vendor/bin/pint --dirty    # Format modified files
vendor/bin/pint --test     # Check without modifying
php artisan test           # Run all tests
php artisan test --filter=[Name]  # Run specific test

# Generation
php artisan make:model Name --factory --migration --seed --policy
php artisan make:livewire Feature/ComponentName --test --pest
php artisan make:request Feature/StoreNameRequest
```

### Type Safety (Mandatory)

```php
// ✅ CORRECT - Always declare types
public function processPayment(int $userId, float $amount): bool
{
    return true;
}

// ❌ WRONG - Missing types
public function processPayment($userId, $amount)
{
    return true;
}
```

### PHPDoc - Types Only

```php
// ✅ Use PHPDoc ONLY for generics and array shapes
/**
 * @param array<string, mixed> $data
 * @return Collection<int, User>
 */
public function processUsers(array $data): Collection

// ❌ NEVER include descriptions
/**
 * Process payment for user          ← NO!
 * @param int $userId The user's ID  ← NO!
 */
```

---

## Conventions

### Laravel/PHP
- PHP 8.1+ features (typed properties, match expressions, enums)
- PSR-12 coding standards (enforced by Pint)
- Use `config()` helper, never `env()` outside config files
- Eloquent relationships with explicit return types
- Form Requests for validation (not inline validation)

### Livewire v4
- Class-based components only (NOT Volt)
- `#[On('event')]` attribute for event listeners (NOT `$listeners` property)
- `#[Computed]` attribute for derived properties
- `#[Locked]` for properties that shouldn't be modified from frontend
- `#[Renderless]` for methods that don't need re-render
- `wire:model.blur` for standard inputs (not `.live`)
- `wire:key` required in loops with unique identifiers
- Typed properties with explicit return types on all methods
- Prevent N+1 queries with eager loading
- New directives: `wire:sort`, `wire:intersect`, `wire:ref`

### Flux UI
- Search Flux documentation before creating custom components
- **ONLY customize with spacing:** `class="mt-4 px-6"`
- **NEVER add:** colors, typography, borders to Flux components
- **LIGHT MODE ONLY** - No `dark:` classes

### Testing
- Integration tests first, unit tests for complex logic
- Pest with `describe` blocks for organization
- Test happy paths AND edge cases
- Always check for N+1 query prevention

---

## Skills

**Before starting any task, check if a relevant skill exists in `.claude/skills/`.**

Load specialized knowledge with: `/skill [skill-name]`

| Skill | Use When |
|-------|----------|
| `clarity` | Need to simplify explanations or avoid over-engineering |
| `honesty` | Uncertain about facts, need to express uncertainty properly |
| `safety` | Declining requests, need to provide alternatives |
| `senior-mindset` | Evaluating tradeoffs, architecture decisions, edge cases |
| `shadcn-ui` | **All public-facing React pages** (landing, auth, job seeker/employer UI) |
| `laravel-feature` | Building new CRUD features or complex business logic |
| `laravel-api` | Creating API endpoints or JSON transformations |
| `livewire-form` | Building data entry forms with validation |
| `laravel-quality` | Before commits or after major changes |
| `laravel-debug` | Troubleshooting issues |
| `laravel-eloquent` | Designing relationships, scopes, eager loading, query optimization |
| `prd-creator` | Writing product requirements documents |
| `skill-creator` | Creating new skills |
| `data-analyst` | Analyzing business data and building KPIs |
| `xlsx` | Creating or editing Excel spreadsheets |

---

## Automated Behaviors (Hooks)

The following automations run via `.claude/settings.json`:

**On Every Prompt:**
- **Skill Suggestions** - Analyzes prompt keywords and suggests relevant skills
- **Constitution Reminders** - Reminds about relevant AI Constitution principles

**After PHP File Edits:**
- **Auto-Format** - Runs `vendor/bin/pint` automatically on modified PHP files

**When Session Ends:**
- **Context Reminder** - Reminds to create context files for multi-day features

---

## Agents

Custom agents for specialized workflows. These are invoked via the Task tool.

| Agent | Purpose |
|-------|---------|
| `laravel-planner` | Senior architect that produces step-by-step implementation plans (read-only, no file edits) |
| `laravel-coder` | Implements approved plans with idiomatic PHP 8.x, Form Requests, Policies |
| `test-runner` | Runs test suite, diagnoses failures, and fixes them while preserving test intent |

**Typical workflow:**
1. `laravel-planner` → Creates implementation plan
2. User approves plan
3. `laravel-coder` → Implements the approved plan
4. `test-runner` → Validates with tests

---

## Autonomous Work (Ralph Wiggum)

For multi-step implementation tasks, use Ralph Wiggum loops:

```bash
/ralph-loop:ralph-loop "<prompt>" --max-iterations 10
/ralph-loop:cancel-ralph   # Stop an active loop
/ralph-loop:help           # Documentation
```

**Use for:** Feature implementation, refactoring, batch fixes, TDD cycles
**Don't use for:** Questions, single edits, exploratory research

---

## Important Reminders

- **ALWAYS** search for similar code before implementing
- **ALWAYS** follow existing conventions (check sibling files)
- **ALWAYS** run Pint before finalizing: `vendor/bin/pint --dirty`
- **ALWAYS** use class-based Livewire (NOT Volt)
- **ALWAYS** use ShadCN UI theme colors for public pages (see `shadcn-ui` skill)
- **NEVER** hardcode colors - use CSS variables (`bg-primary`, `text-foreground`, etc.)
- **NEVER** customize Flux UI colors/typography/borders
- **NEVER** use `env()` outside config files
- **CHECK** Laravel Boost for package-specific guidance
- **ASK** clarifying questions before complex implementations

## Frontend Architecture

| Area | Technology | Theme |
|------|------------|-------|
| Public pages (landing, auth) | ShadCN UI + Inertia/React | Algoma Jobs theme (light & dark) |
| Internal app (admin) | Filament | Filament default |
| Livewire forms | Flux UI Pro | Light mode only |
